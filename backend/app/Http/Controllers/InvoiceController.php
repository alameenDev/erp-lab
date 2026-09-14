<?php

namespace App\Http\Controllers;

use App\Http\Resources\CultureResource;
use App\Http\Resources\TestResource;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\InvoicePaidDetail;
use App\Models\InvoiceTestRel;
use App\Models\Patient;
use App\Models\TestGroup;
use App\Models\TestReferenceRange;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class InvoiceController extends Controller
{
    private $referenceData;

    public function __construct()
    {
        $this->referenceData = cache()->remember('referenceData', 60, function () {
            return [
                'categories' => Category::select('id', 'name')->get(),
                'testGroups' => TestGroup::select('id', 'group_name')->get(),
            ];
        });
    }

    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (! $user->hasPermissionTo(permission: 'invoices view')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $query = Invoice::query();

        // Multi-tenant filtering
        if ($user->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            $query->whereIn('lab_id_fk', $users_ids);
        }

        // Filter: patient_name (searches patient name, barcode, test name/shortcut/report_name)
        if ($request->filled('patient_name')) {
            $term = $request->patient_name;
            $query->where(function ($q) use ($term) {
                $q->whereHas('patient.user', function ($q2) use ($term) {
                    $q2->whereLike('name', '%'.$term.'%');
                })->orWhereLike('barcode', '%'.$term.'%')
                    ->orWhereHas('invoiceTestRels.test', function ($q2) use ($term) {
                        $q2->whereLike('name', '%'.$term.'%')
                            ->orWhereLike('shortcut', '%'.$term.'%')
                            ->orWhereLike('report_name', '%'.$term.'%');
                    });
            });
        }

        // Filter: from_lab
        if ($request->filled('from_lab')) {
            $query->whereHas('fromLab', function ($q) use ($request) {
                $q->whereLike('name', '%'.$request->from_lab.'%');
            });
        }

        // Filter: created_by (lab name)
        if ($request->filled('created_by')) {
            $query->whereHas('lab', function ($q) use ($request) {
                $q->whereLike('name', '%'.$request->created_by.'%');
            });
        }

        // Filter: contract_id_fk (exact match)
        if ($request->filled('contract_id_fk')) {
            $query->where('contract_id_fk', $request->contract_id_fk);
        }

        // Filter: barcode
        if ($request->filled('barcode')) {
            $query->whereLike('barcode', '%'.$request->barcode.'%');
        }

        // Filter: signed_by
        if ($request->filled('signed_by')) {
            $query->whereHas('signedBy', function ($q) use ($request) {
                $q->whereLike('name', '%'.$request->signed_by.'%');
            });
        }

        // Filter: invoice_date (exact date)
        if ($request->filled('invoice_date')) {
            $query->whereDate('created_at', $request->invoice_date);
        }

        // Filter: date_from / date_to (range)
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Filter: status (sent_to_patient for invoices view)
        if ($request->filled('status')) {
            if ($request->status === 'done') {
                $query->where('sent_to_patient', true);
            } elseif ($request->status === 'pending') {
                $query->where(function ($q) {
                    $q->where('sent_to_patient', false)->orWhereNull('sent_to_patient');
                });
            }
        }

        // Filter: is_done (for medical_reports view)
        if ($request->filled('is_done')) {
            if ($request->is_done === 'done') {
                $query->where('is_done', true);
            } elseif ($request->is_done === 'pending') {
                $query->where(function ($q) {
                    $q->where('is_done', false)->orWhereNull('is_done');
                });
            }
        }

        // Filter: signed_status
        if ($request->filled('signed_status')) {
            if ($request->signed_status === 'signed') {
                $query->whereNotNull('signed_by_id_fk');
            } elseif ($request->signed_status === 'unsigned') {
                $query->whereNull('signed_by_id_fk');
            }
        }

        // Filter: payment_status (paid/unpaid/partial)
        if ($request->filled('payment_status')) {
            $paidSub = '(SELECT COALESCE(SUM(amount), 0) FROM invoice_paid_details WHERE invoice_id_fk = invoices.id)';
            if ($request->payment_status === 'paid') {
                $query->whereRaw("$paidSub >= invoices.total AND invoices.total > 0");
            } elseif ($request->payment_status === 'unpaid') {
                $query->whereRaw("$paidSub = 0");
            } elseif ($request->payment_status === 'partial') {
                $query->whereRaw("$paidSub > 0 AND $paidSub < invoices.total");
            }
        }

        // Compute stats from filtered query BEFORE pagination
        $statsQuery = clone $query;
        $stats = $statsQuery
            ->leftJoin(DB::raw('(SELECT invoice_id_fk, COALESCE(SUM(amount), 0) as paid_sum FROM invoice_paid_details GROUP BY invoice_id_fk) as paid_agg'), 'paid_agg.invoice_id_fk', '=', 'invoices.id')
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN invoices.sent_to_patient = true THEN 1 ELSE 0 END) as completed_sent,
                SUM(CASE WHEN invoices.is_done = true THEN 1 ELSE 0 END) as completed_done,
                SUM(CASE WHEN invoices.is_signed = true THEN 1 ELSE 0 END) as signed,
                SUM(CASE WHEN invoices.sent_to_patient = true THEN 1 ELSE 0 END) as sent,
                COALESCE(SUM(invoices.total), 0) as total_amount,
                COALESCE(SUM(paid_agg.paid_sum), 0) as paid_amount,
                COALESCE(SUM(GREATEST(COALESCE(invoices.total, 0) - COALESCE(paid_agg.paid_sum, 0), 0)), 0) as due_amount
            ')->first();

        $statsData = [
            'total' => (int) ($stats->total ?? 0),
            'completed_sent' => (int) ($stats->completed_sent ?? 0),
            'completed_done' => (int) ($stats->completed_done ?? 0),
            'pending_sent' => (int) ($stats->total ?? 0) - (int) ($stats->completed_sent ?? 0),
            'pending_done' => (int) ($stats->total ?? 0) - (int) ($stats->completed_done ?? 0),
            'signed' => (int) ($stats->signed ?? 0),
            'sent' => (int) ($stats->sent ?? 0),
            'total_amount' => (float) ($stats->total_amount ?? 0),
            'paid_amount' => (float) ($stats->paid_amount ?? 0),
            'due_amount' => (float) ($stats->due_amount ?? 0),
        ];

        // Paginate with lightweight relations
        $perPage = $request->input('per_page', 25);
        $invoices = $query
            ->with($this->invoiceListRelations())
            ->latest()
            ->paginate($perPage);

        // Transform for list view (lightweight)
        $transformed = $invoices->getCollection()->map(fn ($invoice) => $this->transformInvoiceForList($invoice));

        return response()->json([
            'data' => $transformed,
            'pagination' => [
                'total' => $invoices->total(),
                'per_page' => $invoices->perPage(),
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'from' => $invoices->firstItem(),
                'to' => $invoices->lastItem(),
            ],
            'stats' => $statsData,
        ]);
    }

    /**
     * Get medical records for a specific patient.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function patientMedicalRecords($id)
    {

        try {
            // Fetch invoices for the patient and order by creation date
            $query = Invoice::where('patient_id_fk', $id)->orderBy('created_at', 'desc');

            $authUser = Auth::user();
            if ($authUser && $authUser->role_id != 1) {
                $users_ids = $this->getTenantUserIds();
                $query->whereIn('lab_id_fk', $users_ids);
            }

            $invoices = $query->with($this->invoiceRelations())->get();
            // Transform invoices for response
            $transformedInvoices = $invoices->map(fn ($invoice) => $this->transformInvoice($invoice));

            // Return transformed invoices as JSON response
            return response()->json(
                $transformedInvoices,
            );

        } catch (Exception $e) {
            // Log error and return error response
            Log::error('Error fetching invoices:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An error occurred while fetching invoices',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Define the relations to be loaded with invoices.
     *
     * @return array
     */
    private function patientHistoryRelations()
    {
        return [
            // Direct invoiceTestRels relations
            'invoiceTestRels.toLab',
            'invoiceTestRels.resultStatus',

            // Existing test relations
            'invoiceTestRels.test.testReferenceRanges.gender',
            'invoiceTestRels.test.testReferenceRanges.ageUnit',
            'invoiceTestRels.test.sample',
            'invoiceTestRels.test.durationUnit',
            'invoiceTestRels.test.testGroup:id,group_name',
            'invoiceTestRels.test.category:id,name',
            'invoiceTestRels.test.resultType',
            'invoiceTestRels.test.lab',
            'invoiceTestRels.test.price_list_rel.priceList:id,name',

            // Existing culture relations
            'invoiceTestRels.culture.attribute.resultType',
            'invoiceTestRels.culture.sample',
            'invoiceTestRels.culture.duration_unit',
            'invoiceTestRels.culture.test_group:id,group_name',
            'invoiceTestRels.culture.category:id,name',
            'invoiceTestRels.culture.lab',
            'invoiceTestRels.culture.price_list_rel.priceList:id,name',

            // Existing package relations
            'invoiceTestRels.package.testGroups.tests.testReferenceRanges.gender',
            'invoiceTestRels.package.testGroups.tests.testReferenceRanges.ageUnit',
            'invoiceTestRels.package.testGroups.tests.sample',
            'invoiceTestRels.package.testGroups.tests.durationUnit',
            'invoiceTestRels.package.testGroups.tests.testGroup:id,group_name',
            'invoiceTestRels.package.testGroups.tests.category:id,name',
            'invoiceTestRels.package.testGroups.tests.resultType',
            'invoiceTestRels.package.testGroups.tests.lab',
            'invoiceTestRels.package.testGroups.tests.questions.answerType',
            'invoiceTestRels.package.testGroups.tests.price_list_rel.priceList:id,name',
            'invoiceTestRels.package.testGroups.culture.attribute.resultType',
            'invoiceTestRels.package.tests.testReferenceRanges.gender',
            'invoiceTestRels.package.tests.testReferenceRanges.ageUnit',
            'invoiceTestRels.package.tests.sample',
            'invoiceTestRels.package.tests.durationUnit',
            'invoiceTestRels.package.tests.testGroup:id,group_name',
            'invoiceTestRels.package.tests.category:id,name',
            'invoiceTestRels.package.tests.resultType',
            'invoiceTestRels.package.tests.lab',
            'invoiceTestRels.package.tests.questions.answerType',
            'invoiceTestRels.package.tests.price_list_rel.priceList:id,name',
            'invoiceTestRels.package.cultures.attribute.resultType',
            'invoiceTestRels.package.cultures.price_list_rel.priceList:id,name',
            'invoiceTestRels.package.cultures.sample',
            'invoiceTestRels.package.cultures.duration_unit',
            'invoiceTestRels.package.cultures.test_group:id,group_name',
            'invoiceTestRels.package.cultures.category:id,name',
            'invoiceTestRels.package.cultures.lab',
            'invoiceTestRels.package.cultures.price_list_rel.priceList:id,name',
            'invoiceTestRels.package.lab',
            'invoiceTestRels.package.price_list_rel.priceList:id,name',

            // Existing test group relations
            'invoiceTestRels.testGroup.tests.testReferenceRanges.gender',
            'invoiceTestRels.testGroup.tests.testReferenceRanges.ageUnit',
            'invoiceTestRels.testGroup.tests.sample',
            'invoiceTestRels.testGroup.tests.durationUnit',
            'invoiceTestRels.testGroup.tests.testGroup:id,group_name',
            'invoiceTestRels.testGroup.tests.category:id,name',
            'invoiceTestRels.testGroup.tests.resultType',
            'invoiceTestRels.testGroup.tests.lab',
            'invoiceTestRels.testGroup.tests.price_list_rel.priceList:id,name',
            'invoiceTestRels.testGroup.tests.questions.answerType',
            'invoiceTestRels.testGroup.culture.attribute.resultType',
            'invoiceTestRels.testGroup.culture.sample',
            'invoiceTestRels.testGroup.culture.duration_unit',
            'invoiceTestRels.testGroup.culture.test_group:id,group_name',
            'invoiceTestRels.testGroup.culture.category:id,name',
            'invoiceTestRels.testGroup.culture.lab',
            'invoiceTestRels.testGroup.culture.price_list_rel.priceList:id,name',
            'invoiceTestRels.testGroup.sample',
        ];
    }

    /**
     * Get invoice history for a specific patient.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function patientInvoicesHistory($id)
    {
        // Fetch invoices for the patient and order by creation date
        $query = Invoice::where('patient_id_fk', $id)->where('is_done', 1);

        $authUser = Auth::user();
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            $query->whereIn('lab_id_fk', $users_ids);
        }

        $invoices = $query->latest()
            ->with($this->patientHistoryRelations())
            ->get();

        // Return a message if no invoices are found
        if ($invoices->isEmpty()) {
            return response()->json(['message' => 'No records found'], 200);
        }
        $tests = $test_groups = $cultures = $packages = [];

        // Process each invoice and its relations
        foreach ($invoices as $invoice) {
            foreach ($invoice->invoiceTestRels as $rel) {
                $this->processRelation($rel, $tests, $cultures, $packages, $test_groups, $this->referenceData);
            }
        }

        // Return processed data as JSON response
        return response()->json([
            'tests' => $tests,
            'cultures' => $cultures,
            'packages' => $packages,
            'test_groups' => $test_groups,
        ]);
    }

    /**
     * Define the relations to be loaded with invoices.
     *
     * @return array
     */
    private function invoiceRelations()
    {
        return [
            // Existing test relations
            'invoiceTestRels.test.testReferenceRanges.gender',
            'invoiceTestRels.test.testReferenceRanges.ageUnit',
            'invoiceTestRels.test.sample',
            'invoiceTestRels.test.durationUnit',
            'invoiceTestRels.test.testGroup:id,group_name',
            'invoiceTestRels.test.category:id,name',
            'invoiceTestRels.test.resultType',
            'invoiceTestRels.test.lab',
            'invoiceTestRels.test.price_list_rel.priceList:id,name',

            // Existing culture relations
            'invoiceTestRels.culture.attribute.resultType',
            'invoiceTestRels.culture.sample',
            'invoiceTestRels.culture.duration_unit',
            'invoiceTestRels.culture.test_group:id,group_name',
            'invoiceTestRels.culture.category:id,name',
            'invoiceTestRels.culture.lab',
            'invoiceTestRels.culture.price_list_rel.priceList:id,name',

            // Existing package relations
            'invoiceTestRels.package.testGroups.tests.testReferenceRanges.gender',
            'invoiceTestRels.package.testGroups.tests.testReferenceRanges.ageUnit',
            'invoiceTestRels.package.testGroups.tests.sample',
            'invoiceTestRels.package.testGroups.tests.durationUnit',
            'invoiceTestRels.package.testGroups.tests.testGroup:id,group_name',
            'invoiceTestRels.package.testGroups.tests.category:id,name',
            'invoiceTestRels.package.testGroups.tests.resultType',
            'invoiceTestRels.package.testGroups.tests.lab',
            'invoiceTestRels.package.testGroups.tests.questions.answerType',
            'invoiceTestRels.package.testGroups.tests.price_list_rel.priceList:id,name',
            'invoiceTestRels.package.testGroups.culture.attribute.resultType',
            'invoiceTestRels.package.tests.testReferenceRanges.gender',
            'invoiceTestRels.package.tests.testReferenceRanges.ageUnit',
            'invoiceTestRels.package.tests.sample',
            'invoiceTestRels.package.tests.durationUnit',
            'invoiceTestRels.package.tests.testGroup:id,group_name',
            'invoiceTestRels.package.tests.category:id,name',
            'invoiceTestRels.package.tests.resultType',
            'invoiceTestRels.package.tests.lab',
            'invoiceTestRels.package.tests.questions.answerType',
            'invoiceTestRels.package.tests.price_list_rel.priceList:id,name',
            'invoiceTestRels.package.cultures.attribute.resultType',
            'invoiceTestRels.package.cultures.price_list_rel.priceList:id,name',
            'invoiceTestRels.package.cultures.sample',
            'invoiceTestRels.package.cultures.duration_unit',
            'invoiceTestRels.package.cultures.test_group:id,group_name',
            'invoiceTestRels.package.cultures.category:id,name',
            'invoiceTestRels.package.cultures.lab',
            'invoiceTestRels.package.cultures.price_list_rel.priceList:id,name',
            'invoiceTestRels.package.lab',
            'invoiceTestRels.package.price_list_rel.priceList:id,name',

            // Existing test group relations
            'invoiceTestRels.testGroup.tests.testReferenceRanges.gender',
            'invoiceTestRels.testGroup.tests.testReferenceRanges.ageUnit',
            'invoiceTestRels.testGroup.tests.sample',
            'invoiceTestRels.testGroup.tests.durationUnit',
            'invoiceTestRels.testGroup.tests.testGroup:id,group_name',
            'invoiceTestRels.testGroup.tests.category:id,name',
            'invoiceTestRels.testGroup.tests.resultType',
            'invoiceTestRels.testGroup.tests.lab',
            'invoiceTestRels.testGroup.tests.price_list_rel.priceList:id,name',
            'invoiceTestRels.testGroup.tests.questions.answerType',
            'invoiceTestRels.testGroup.culture.attribute.resultType',
            'invoiceTestRels.testGroup.culture.sample',
            'invoiceTestRels.testGroup.culture.duration_unit',
            'invoiceTestRels.testGroup.culture.test_group:id,group_name',
            'invoiceTestRels.testGroup.culture.category:id,name',
            'invoiceTestRels.testGroup.culture.lab',
            'invoiceTestRels.testGroup.culture.price_list_rel.priceList:id,name',
            'invoiceTestRels.testGroup.sample',

            // Result status relation
            'invoiceTestRels.resultStatus',
            'invoiceTestRels.toLab',

            // Existing patient relations
            'patient.user.role',
            'patient.title',
            'patient.gender',
            'patient.ageUnit',

            // Existing payment relations
            'paidDetails.paymentMethod',

            // Existing referral relations
            'referral.referals',
            'referral.role',

            // Other existing relations
            'lab',
            'fromLab:id,name',
            'discountType',
            'sampleCollector.role',
            'sampleCollector.referals',
            'sampleCollector.lab',
            'signedBy:id,name',
            'contract',
        ];
    }

    /**
     * Lightweight relations for the list/index view.
     * Only loads what the table columns need — no test/culture/package data.
     */
    private function invoiceListRelations()
    {
        return [
            'patient.user:id,name,phone_number,email,address',
            'patient.gender',
            'patient.ageUnit',
            'patient.title',
            'lab:id,name',
            'fromLab:id,name',
            'contract:id,name',
            'signedBy:id,name',
            'paidDetails.paymentMethod:id,name',
            'discountType:id,type',
        ];
    }

    /**
     * Transform an invoice for the list view (lightweight).
     * Skips all test/culture/package processing.
     */
    private function transformInvoiceForList($invoice)
    {
        return [
            'id' => $invoice->id,
            'barcode' => $invoice->barcode,
            'patient' => $invoice->patient ? $this->transformPatient($invoice->patient) : null,
            'lab' => $invoice->lab?->name,
            'from_lab' => $invoice->fromLab?->name,
            'from_lab_id_fk' => $invoice->from_lab_id_fk,
            'contract' => $invoice->contract,
            'contract_id_fk' => $invoice->contract_id_fk,
            'signed_by' => $invoice->signedBy,
            'is_signed' => $invoice->is_signed == 1,
            'is_done' => $invoice->is_done == 1,
            'sent_to_patient' => $invoice->sent_to_patient == 1,
            'total' => $invoice->total,
            'sub_total' => $invoice->sub_total,
            'paid' => $invoice->paidDetails->sum('amount'),
            'discount' => $invoice->discount,
            'discount_type' => $invoice->discountType?->type,
            'sample_collector' => $invoice->sample_collector_id_fk,
            'notes' => $invoice->notes,
            'payment_details' => $invoice->paidDetails->map(fn ($d) => [
                'id' => $d->id,
                'amount' => $d->amount,
                'payment_method' => $d->paymentMethod?->name,
                'paid_at' => $d->created_at,
            ]),
            'payment_status' => $this->getPaymentStatus($invoice),
            'qr_code' => $invoice->qr_code,
            'registration_date' => $invoice->created_at,
            'created_at' => $invoice->created_at,
            'created_by' => $invoice->lab,
        ];
    }

    /**
     * Transform an invoice for response.
     *
     * @param  Invoice  $invoice
     * @return array
     */
    private function transformInvoice($invoice)
    {
        $test_groups = [];
        $tests = $cultures = $packages = $cultures_last_results = $tests_last_results = [];
        $result_comments_tests = [];
        $result_comments_cultures = [];
        $result_package_comments = [];
        $test_group_comments = [];

        // Pre-fetch last results to avoid N+1 queries
        $lastResultsMap = $this->batchLoadLastResults($invoice);

        // Process each relation of the invoice
        foreach ($invoice->invoiceTestRels as $rel) {
            $this->processRelation($rel, $tests, $cultures, $packages, $test_groups, $this->referenceData);

            // Collect result comments for tests
            $this->collectResultComments($rel, $result_comments_tests, $result_comments_cultures, $result_package_comments, $test_group_comments);

            // Process last result data
            if ($rel->last_result && $rel->last_result_data === null) {
                // Determine if we are looking for a test or culture
                $isTest = ! empty($rel->test_id_fk);
                $queryValue = $isTest ? $rel->test_id_fk : $rel->culture_id_fk;
                $mapKey = ($isTest ? 'test_' : 'culture_').$queryValue;

                $lastResultRel = $lastResultsMap->get($mapKey);

                if ($lastResultRel) {
                    $item = $isTest ? $lastResultRel->test : $lastResultRel->culture;

                    $data = [
                        'name' => $item?->name,
                        'result' => $lastResultRel->result,
                        'status' => $lastResultRel->resultStatus?->status,
                        'status_id_fk' => $lastResultRel->result_status_id_fk,
                        'comment' => $lastResultRel->comment,
                        'invoice_id' => $lastResultRel->invoice_id_fk,
                        'result_date' => $lastResultRel->invoice->result_date,
                    ];

                    if ($isTest) {
                        $data['test_reference_ranges'] = $item?->testReferenceRanges->map(fn ($range) => $this->transformTestReferenceRange($range));
                        $rel->last_result_data = $data;
                        $tests_last_results[] = $data;
                    } else {
                        $data['attribute'] = $lastResultRel->attribute;
                        $rel->last_result_data = $data;
                        $cultures_last_results[] = $data;
                    }
                    // Removed $rel->save() to prevent write-on-read
                }
            } elseif ($rel->last_result && $rel->last_result_data !== null) {
                if (! empty($rel->test_id_fk)) {
                    $tests_last_results[] = $rel->last_result_data;
                } else {
                    $cultures_last_results[] = $rel->last_result_data;
                }
            }
        }

        // Combine all tests and cultures from packages and test groups
        $all_tests = [];
        $all_cultures = [];
        foreach ($packages as $package) {
            foreach ($package['tests'] as $test) {
                $all_tests[] = $test;
            }
            foreach ($package['cultures'] as $culture) {
                $all_cultures[] = $culture;
            }
        }
        foreach ($test_groups as $test_group) {
            foreach ($test_group['tests'] as $test) {
                $all_tests[] = $test;
            }
            foreach ($test_group['cultures'] as $culture) {
                $all_cultures[] = $culture;
            }
        }
        foreach ($tests as $test) {
            $all_tests[] = $test;
        }
        foreach ($cultures as $culture) {
            $all_cultures[] = $culture;
        }

        // Separate tests that should be printed alone
        $all_tests_print_alone = collect($all_tests)->filter(fn ($test) => $test['is_print_alone'] == 1)->values();
        $all_tests_not_print_alone = collect($all_tests)->filter(fn ($test) => $test['is_print_alone'] == 0)->values();

        // Collect unique test group names
        $test_groups_names = [];
        foreach ($all_tests as $test) {
            $test_groups_names[] = is_object($test['test_group'] ?? null) ? $test['test_group']->group_name : $test['test_group'] ?? null;
        }
        foreach ($all_cultures as $culture) {
            $test_groups_names[] = is_object($culture['test_group'] ?? null) ? $culture['test_group']->group_name : $culture['test_group'] ?? null;
        }

        $test_groups_names = collect($test_groups_names)
            ->unique()
            ->values()
            ->all();
        $test_groups_data = [];

        // Fetch categories for the lab
        $categories = $this->referenceData['categories'];
        foreach ($test_groups_names as $test_group_name) {
            $category = collect($categories)->filter(fn ($category) => $this->referenceData['testGroups']->contains('group_name', $test_group_name))->first();
            $test_groups_data[] = [
                'name' => $test_group_name,
                'category' => $category?->name,
                'tests_print_alone' => collect($all_tests_print_alone)->filter(fn ($test) => $test['test_group'] ?? $test_group_name == null)->values(),
                'tests_not_print_alone' => collect($all_tests_not_print_alone)->filter(fn ($test) => $test['test_group'] ?? $test_group_name == null)->values(),
                'cultures' => collect($all_cultures)->filter(fn ($culture) => $culture['test_group'] ?? $test_group_name == null)->values(),
            ];
        }

        // Return transformed invoice data
        return [
            'id' => $invoice->id,
            'patient_id_fk' => $invoice->patient_id_fk,
            'lab_id_fk' => $invoice->lab_id_fk,
            'sent_to_patient' => $invoice->sent_to_patient == 1,
            'patient' => $invoice->patient ? $this->transformPatient($invoice->patient) : null,
            'paidDetails' => $invoice->paidDetails ? $invoice->paidDetails?->map(fn ($detail) => $this->transformPaidDetail($detail)) : null,
            'referral' => $this->transformReferral($invoice->referral),
            'registration_date' => $invoice->created_at,
            'result_date' => $invoice->result_date,
            'sub_total' => $invoice->sub_total,
            'lab' => $invoice->lab?->name,
            'total' => $invoice->total,
            'paid' => $invoice->paidDetails->sum('amount'),
            'notes' => $invoice->notes,
            'is_printed' => $invoice->is_printed == 1,
            'is_signed' => $invoice->is_signed == 1,
            'signed_by' => $invoice->signedBy,
            'is_done' => $invoice->is_done == 1,
            'public_with_background' => (bool) $invoice->public_with_background,
            'qr_code' => $invoice->qr_code,
            'pdf_qr_code' => $invoice->pdf_qr_code,
            'result_doc' => $invoice->result_doc,
            'barcode' => $invoice->barcode,
            'created_by' => $invoice->lab,
            'from_lab_id_fk' => $invoice->from_lab_id_fk,
            'from_lab' => $invoice->fromLab?->name,
            'contract' => $invoice->contract,
            'contract_id_fk' => $invoice->contract_id_fk,
            'sample_collector' => $invoice->sampleCollector ? [
                'id' => $invoice->sampleCollector?->id,
                'name' => $invoice->sampleCollector?->name,
                'commission' => $invoice->sampleCollector?->role_id == 6 ? $invoice->sampleCollector?->referals?->commission : $invoice->sampleCollector?->lab?->discount_percentage,
                'discount_percentage' => $invoice->sampleCollector?->role_id == 6 ? $invoice->sampleCollector?->referals?->commission : $invoice->sampleCollector?->lab?->discount_percentage,
            ] : null,
            'discount_type' => $invoice->discountType?->type,
            'discount_type_id_fk' => $invoice->discount_type_id_fk,
            'discount' => $invoice->discount,
            'show_result_date' => $invoice->show_result_date == 1,
            'show_patient_card_id' => $invoice->show_patient_card_id == 1,
            'show_patient_pic' => $invoice->show_patient_pic == 1,
            'tests_comment' => $invoice->tests_comment == 'null' ? null : $invoice->tests_comment,
            'cultures_comment' => $invoice->cultures_comment == 'null' ? null : $invoice->cultures_comment,
            'package_comments' => $invoice->packages_comment == 'null' ? null : $invoice->packages_comment,
            'result_comments_tests' => collect($result_comments_tests)->filter()->values()->all(),
            'result_comments_cultures' => collect($result_comments_cultures)->filter()->values()->all(),
            'result_package_comments' => collect($result_package_comments)->filter()->values()->all(),
            'attachments' => is_array($invoice->attachments) ? array_values(array_filter(collect($invoice->attachments)->filter()->values()->all())) : [],
            'tests' => array_values(array_filter($tests)),
            'cultures' => array_values(array_filter($cultures)),
            'packages' => array_values(array_filter($packages)),
            'test_groups' => array_values(array_filter($test_groups)),
            'test_groups_all' => $test_groups_data,
            'test_group_comments' => array_values(array_filter($test_group_comments)),
            'tests_last_results' => array_values(array_filter($tests_last_results)),
            'cultures_last_results' => array_values(array_filter($cultures_last_results)),
            'created_at' => $invoice->created_at,
            'updated_at' => $invoice->updated_at,
        ];
    }

    /**
     * Batch load last results for all tests/cultures that need them.
     * This prevents N+1 queries when fetching last results in transformInvoice.
     *
     * @param  Invoice  $invoice
     * @return Collection
     */
    private function batchLoadLastResults($invoice)
    {
        // Collect test_ids and culture_ids that need last result lookup
        $testIds = [];
        $cultureIds = [];

        foreach ($invoice->invoiceTestRels as $rel) {
            if ($rel->last_result && $rel->last_result_data === null) {
                if (! empty($rel->test_id_fk)) {
                    $testIds[] = $rel->test_id_fk;
                } elseif (! empty($rel->culture_id_fk)) {
                    $cultureIds[] = $rel->culture_id_fk;
                }
            }
        }

        $lastResultsMap = collect();

        // Batch fetch last results for tests
        if (! empty($testIds)) {
            $testLastResults = InvoiceTestRel::whereIn('test_id_fk', array_unique($testIds))
                ->where('invoice_id_fk', '!=', $invoice->id)
                ->where('is_done', true)
                ->whereHas('invoice', function ($query) use ($invoice): void {
                    $query->where('patient_id_fk', $invoice->patient_id_fk);
                })
                ->with(['resultStatus', 'test.testReferenceRanges', 'invoice:id,result_date'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('test_id_fk')
                ->map(fn ($group) => $group->first());

            foreach ($testLastResults as $testId => $result) {
                $lastResultsMap->put('test_'.$testId, $result);
            }
        }

        // Batch fetch last results for cultures
        if (! empty($cultureIds)) {
            $cultureLastResults = InvoiceTestRel::whereIn('culture_id_fk', array_unique($cultureIds))
                ->where('invoice_id_fk', '!=', $invoice->id)
                ->where('is_done', true)
                ->whereHas('invoice', function ($query) use ($invoice): void {
                    $query->where('patient_id_fk', $invoice->patient_id_fk);
                })
                ->with(['resultStatus', 'culture', 'invoice:id,result_date'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('culture_id_fk')
                ->map(fn ($group) => $group->first());

            foreach ($cultureLastResults as $cultureId => $result) {
                $lastResultsMap->put('culture_'.$cultureId, $result);
            }
        }

        return $lastResultsMap;
    }

    /**
     * Process a relation and add it to the appropriate array.
     *
     * @param  InvoiceTestRel  $rel
     * @param  array  $tests
     * @param  array  $cultures
     * @param  array  $packages
     * @param  array  $test_groups
     */
    private function processRelation($rel, &$tests, &$cultures, &$packages, &$test_groups, $referenceData)
    {
        if ($rel->test) {
            $tests[] = $this->transformTest($rel, $referenceData);
        } elseif ($rel->culture) {
            $cultures[] = $this->transformCulture($rel, $referenceData);
        } elseif ($rel->package) {
            $packages[] = $this->transformPackage($rel);
        } elseif ($rel->testGroup) {
            $test_groups[] = $this->transformTestGroup($rel, $referenceData);
        }
    }

    /**
     * Transform a test relation for response.
     *
     * @param  InvoiceTestRel  $rel
     * @return array
     */
    private function transformTest($rel, $referenceData)
    {
        return [

            'test_id_fk' => $rel->test_id_fk,
            'name' => $rel->test?->name,
            'shortcut' => $rel->test?->shortcut,
            'report_name' => $rel->test?->report_name,
            'unit' => $rel->test?->unit,
            'to_lab_id_fk' => $rel->to_lab_id_fk,
            'to_lab' => $rel->toLab?->name,
            'selection_type_options' => $rel->test?->selection_type_options,
            'test_reference_ranges' => $rel->test?->testReferenceRanges->map(fn ($range) => $this->transformTestReferenceRange($range)),
            'sample_name' => $rel->test?->sample?->sample_name,
            'price' => $rel->price,
            'prices' => empty($rel->test?->price_list_rel) ? [] : $rel->test->price_list_rel->map(function ($price) {
                return [
                    'id' => $price->id,
                    'price_list_id' => $price->priceList?->id,
                    'price_list_title' => $price->priceList?->name,
                    'original_price' => $price->original_price,
                    'price_for_customer' => $price->price_for_customer - ($price->price_for_customer * ($price->priceList?->discount ?? 0) / 100),
                ];
            }),
            'is_sample_received' => $rel->is_sample_received == 1,
            'questions' => $rel->questions,
            'test_group' => $referenceData['testGroups']->where('id', $rel->test?->test_group_id_fk)->first()?->group_name,
            'result_type_id_fk' => $rel->test?->result_type_id_fk,
            'test_group_id_fk' => $rel->test?->test_group_id_fk,
            'category' => $referenceData['categories']->where('id', $rel->test?->category_id_fk)->first()?->name,
            'category_id_fk' => $rel->test?->category_id_fk,
            'is_special_test' => $rel->test?->is_special_test == 1,
            'content' => $rel->content ?? $rel->test?->content,
            'sub_tests' => $rel->sub_tests ?? $rel->test?->sub_tests,
            'result' => $rel->result,
            'is_done' => $rel->is_done == 1,
            'is_print_alone' => $rel->test?->is_print_alone == 1,
            'status' => $rel->resultStatus?->status,
            'result_status_id_fk' => $rel->result_status_id_fk,
            'result_status_text' => $rel->result_status_text,
            'comment' => $rel->comment,
            'last_result' => $rel->last_result,
            'result_comments' => $rel->test?->result_comments,
            'created_at' => $rel->created_at,
            'updated_at' => $rel->updated_at,
        ];
    }

    /**
     * Transform a culture relation for response.
     *
     * @param  InvoiceTestRel  $rel
     * @return array
     */
    private function transformCulture($rel, $referenceData)
    {
        return [

            'culture_id_fk' => $rel->culture_id_fk,
            'name' => $rel->culture?->name,
            'is_done' => $rel->is_done == 1,
            'to_lab_id_fk' => $rel->to_lab_id_fk,
            'to_lab' => $rel->toLab?->name,
            'sample_id_fk' => $rel->culture?->sample_id_fk,
            'sample_name' => $rel->culture?->sample?->sample_name,
            'attribute' => $rel->attribute == null ? ($rel->culture?->attribute != null ? collect($rel->culture->attribute)->sortBy('order')->values() : null) : ($rel->attribute != null ? collect($rel->attribute)->sortBy('order')->values() : null),
            'price' => $rel->price,
            'is_sample_received' => $rel->is_sample_received == 1,
            'questions' => $rel->questions,
            'test_group' => $referenceData['testGroups']->where('id', $rel->culture?->test_group_id_fk)->first()?->group_name,
            'test_group_id_fk' => $rel->culture?->test_group_id_fk,
            'category' => $referenceData['categories']->where('id', $rel->culture?->category_id_fk)->first()?->name,
            'category_id_fk' => $rel->culture?->category_id_fk,
            'result' => $rel->result,
            'status' => $rel->resultStatus?->status,
            'comment' => $rel->comment,
            'result_status_id_fk' => $rel->result_status_id_fk,
            'result_status_text' => $rel->result_status_text,
            'last_result' => $rel->last_result,
            'result_comments' => $rel->culture?->result_comments,
            'created_at' => $rel->created_at,
            'updated_at' => $rel->updated_at,
        ];
    }

    /**
     * Enrich stored tests JSON with metadata from the database models.
     * Stored JSON has results but lacks sample_name, report_name, testReferenceRanges, etc.
     */
    private function enrichStoredTests($storedTests, $modelTests)
    {
        $stored = is_string($storedTests) ? json_decode($storedTests, true) : (is_array($storedTests) ? $storedTests : []);
        // Handle double-encoded JSON (string after first decode)
        if (is_string($stored)) {
            $stored = json_decode($stored, true) ?? [];
        }
        if (empty($stored)) {
            return $stored;
        }

        $freshByName = collect(TestResource::collection($modelTests ?? [])->resolve())
            ->keyBy('name');

        // These describe the TEST DEFINITION, not the entered result, so the live
        // Test model wins whenever the stored snapshot has nothing usable.
        // `??` was not enough: a snapshot may hold "" / [] / false (not null),
        // which `??` keeps — that made a special test stored inside a test group
        // come back with empty content/sub_tests and is_special_test=false, so the
        // UI rendered it as an ordinary test instead of its custom template.
        // Entered values are preserved because a non-empty stored value still wins.
        $preferStored = static function ($storedValue, $freshValue, $default = null) {
            $isEmpty = $storedValue === null
                || $storedValue === ''
                || $storedValue === false
                || (is_array($storedValue) && count($storedValue) === 0);

            if (! $isEmpty) {
                return $storedValue;
            }

            return $freshValue ?? $default;
        };

        return collect($stored)->map(function ($item) use ($freshByName, $preferStored) {
            $fresh = $freshByName->get($item['name'] ?? '') ?? $freshByName->get($item['report_name'] ?? '');
            if ($fresh) {
                $item['sample_name'] = $item['sample_name'] ?? $fresh['sample_name'] ?? null;
                $item['sample_id_fk'] = $item['sample_id_fk'] ?? $fresh['sample_id_fk'] ?? null;
                $item['shortcut'] = $item['shortcut'] ?? $fresh['shortcut'] ?? null;
                $item['report_name'] = $item['report_name'] ?? $fresh['report_name'] ?? null;
                $item['test_reference_ranges'] = $preferStored($item['test_reference_ranges'] ?? null, $fresh['test_reference_ranges'] ?? null, []);
                $item['unit'] = $item['unit'] ?? $fresh['unit'] ?? null;
                $item['result_type_id_fk'] = $preferStored($item['result_type_id_fk'] ?? null, $fresh['result_type_id_fk'] ?? null);
                $item['result_type_name'] = $item['result_type_name'] ?? $fresh['result_type_name'] ?? null;
                $item['selction_type_options'] = $preferStored($item['selction_type_options'] ?? null, $fresh['selction_type_options'] ?? null);
                $item['is_special_test'] = $preferStored($item['is_special_test'] ?? null, $fresh['is_special_test'] ?? null, false);
                $item['content'] = $preferStored($item['content'] ?? null, $fresh['content'] ?? null);
                $item['sub_tests'] = $preferStored($item['sub_tests'] ?? null, $fresh['sub_tests'] ?? null);
                $item['result_comments'] = $item['result_comments'] ?? $fresh['result_comments'] ?? null;
            }

            return $item;
        })->values()->all();
    }

    /**
     * Enrich stored cultures JSON with metadata from the database models.
     */
    private function enrichStoredCultures($storedCultures, $modelCultures)
    {
        $stored = is_string($storedCultures) ? json_decode($storedCultures, true) : (is_array($storedCultures) ? $storedCultures : []);
        // Handle double-encoded JSON
        if (is_string($stored)) {
            $stored = json_decode($stored, true) ?? [];
        }
        if (empty($stored)) {
            return $stored;
        }

        $freshByName = collect(CultureResource::collection($modelCultures ?? [])->resolve())
            ->keyBy('name');

        return collect($stored)->map(function ($item) use ($freshByName) {
            $fresh = $freshByName->get($item['name'] ?? '');
            if ($fresh) {
                $item['sample_name'] = $item['sample_name'] ?? ($fresh['sample'] ?? null);
                $item['sample_id_fk'] = $item['sample_id_fk'] ?? $fresh['sample_id_fk'] ?? null;
                $item['attribute'] = $item['attribute'] ?? $fresh['attribute'] ?? null;
                $item['result_comments'] = $item['result_comments'] ?? $fresh['result_comments'] ?? null;
            }

            return $item;
        })->values()->all();
    }

    /**
     * Transform a package relation for response.
     *
     * @param  InvoiceTestRel  $rel
     * @return array
     */
    /**
     * Package tests for the invoice payload = the package's own tests PLUS the
     * tests of every whole test group attached to the package. Group tests are
     * tagged with test_group_name so the UI can caption them, and are skipped
     * when already present (stored snapshots of newer invoices contain them).
     */
    private function packageTestsWithGroups($rel, bool $hasStoredTests)
    {
        $tests = $hasStoredTests
            ? collect($this->enrichStoredTests($rel->package_tests, $rel->package?->tests))
            : collect(TestResource::collection($rel->package?->tests ?? [])->resolve());

        $seen = $tests->pluck('name')->filter()->all();

        foreach ($rel->package?->testGroups ?? [] as $group) {
            foreach (TestResource::collection($group->tests ?? [])->resolve() as $t) {
                if (in_array($t['name'] ?? null, $seen, true)) {
                    continue;
                }
                $t['test_group_name'] = $group->group_name;
                $t['test_group_id_fk'] = $group->id;
                $tests->push($t);
                $seen[] = $t['name'] ?? null;
            }
        }

        return $tests->values();
    }

    private function transformPackage($rel)
    {
        $hasStoredTests = $rel->package_tests != null && $rel->package_tests != 'null';
        $hasStoredCultures = $rel->package_cultures != null && $rel->package_cultures != 'null';

        return [

            'package_id_fk' => $rel->package_id_fk,
            'name' => $rel->package?->name,
            'formula' => $rel->package?->formula,
            'to_lab_id_fk' => $rel->to_lab_id_fk,
            'to_lab' => $rel->toLab?->name,
            'is_done' => $rel->is_done == 1,
            'sample_name' => null,
            'price' => $rel->price,
            'cultures' => $hasStoredCultures ? $this->enrichStoredCultures($rel->package_cultures, $rel->package?->cultures) : CultureResource::collection($rel->package?->cultures ?? []),
            'tests' => $this->packageTestsWithGroups($rel, $hasStoredTests),
            'test_groups' => collect($rel->package?->testGroups ?? [])->map(fn ($g) => [
                'test_group_id_fk' => $g->id,
                'group_name' => $g->group_name,
                'shortcut' => $g->shortcut,
                'formula' => $g->formula,
                'tests' => TestResource::collection($g->tests ?? [])->resolve(),
                'cultures' => CultureResource::collection($g->culture ?? [])->resolve(),
            ])->values(),
            'is_sample_received' => $rel->is_sample_received == 1,
            'questions' => $rel->questions,
            'result' => $rel->result,
            'status' => $rel->resultStatus?->status,
            'comment' => $rel->comment,
            'last_result' => $rel->last_result,
            'result_status_id_fk' => $rel->result_status_id_fk,
            'result_status_text' => $rel->result_status_text,
            'created_at' => $rel->created_at,
            'updated_at' => $rel->updated_at,
        ];
    }

    /**
     * Transform a test group relation for response.
     *
     * @param  InvoiceTestRel  $rel
     * @return array
     */
    private function transformTestGroup($rel, $referenceData)
    {
        $hasStoredTests = $rel->test_group_tests != null && $rel->test_group_tests != 'null';
        $hasStoredCultures = $rel->test_group_cultures != null && $rel->test_group_cultures != 'null';

        return [

            'test_group_id_fk' => $rel->test_group_id_fk,
            'group_name' => $rel->testGroup?->group_name,
            'formula' => $rel->testGroup?->formula,
            'is_print_alone' => $rel->testGroup?->is_print_alone == 1,
            'to_lab_id_fk' => $rel->to_lab_id_fk,
            'to_lab' => $rel->toLab?->name,
            'is_done' => $rel->is_done == 1,
            'price' => $rel->price,
            'cultures' => $hasStoredCultures ? $this->enrichStoredCultures($rel->test_group_cultures, $rel->testGroup?->cultures) : CultureResource::collection($rel->testGroup?->cultures ?? []),
            'tests' => $hasStoredTests ? $this->enrichStoredTests($rel->test_group_tests, $rel->testGroup?->tests) : TestResource::collection($rel->testGroup?->tests ?? []),
            'is_sample_received' => $rel->is_sample_received == 1,
            'questions' => $rel->questions,
            'result' => $rel->result,
            'status' => $rel->resultStatus?->status,
            'comment' => $rel->comment,
            'last_result' => $rel->last_result,
            'result_status_id_fk' => $rel->result_status_id_fk,
            'result_status_text' => $rel->result_status_text,
            'created_at' => $rel->created_at,
            'updated_at' => $rel->updated_at,
        ];
    }

    /**
     * Transform a test reference range for response.
     *
     * @param  TestReferenceRange  $range
     * @return array
     */
    private function transformTestReferenceRange($range)
    {
        return [
            'test_reference_range_id' => $range?->id,
            'from' => $range?->from,
            'to' => $range?->to,
            'gender_id_fk' => $range?->gender_id_fk,
            'gender' => $range?->gender?->gender_type,
            'age_from' => $range?->age_from,
            'age_to' => $range?->age_to,
            'age_unit_id_fk' => $range?->age_unit_id_fk,
            'age_unit' => $range?->ageUnit?->unit_name,
            'test_reference_options' => $range?->selection_type_options,
            'notes' => $range?->notes,
        ];
    }

    /**
     * Transform a patient for response.
     *
     * @param  Patient  $patient
     * @return array
     */
    private function getPaymentStatus($invoice): string
    {
        $paid = $invoice->paidDetails->sum('amount');
        $total = $invoice->total ?? 0;
        if ($total <= 0) {
            return 'unpaid';
        }
        if ($paid >= $total) {
            return 'paid';
        }
        if ($paid > 0) {
            return 'partial';
        }

        return 'unpaid';
    }

    private function transformPatient($patient)
    {
        return [
            'id' => $patient?->id,
            'name' => $patient?->user?->name,
            'phone' => $patient?->user?->phone_number,
            'email' => $patient?->user?->email,
            'address' => $patient?->user?->address,
            'national_id_no' => $patient?->national_id_no,
            'code' => $patient?->code,
            'title_id_fk' => $patient?->title_id_fk,
            'title' => $patient?->title?->title,
            'dob' => $patient?->dob,
            'gender_id_fk' => $patient?->gender_id_fk,
            'gender' => $patient?->gender?->gender_type,
            'age' => $patient?->age,
            'age_unit_id_fk' => $patient?->age_unit_id_fk,
            'age_unit' => $patient?->ageUnit?->unit_name,
        ];
    }

    /**
     * Transform a paid detail record into a standardized array format.
     * Includes payment method, contract info, and amount details.
     */
    private function transformPaidDetail($detail)
    {
        return [
            'id' => $detail->id,
            'payment_method_id_fk' => $detail->payment_method_id_fk,
            'payment_method' => $detail->paymentMethod?->name,
            // 'contract' => $detail->contract,
            'contract_id_fk' => $detail->contract_id_fk,
            'amount' => $detail->amount,
            'created_at' => $detail->created_at,
            'updated_at' => $detail->updated_at,
        ];
    }

    /**
     * Transform a referral record into a standardized array format.
     * Includes user details, commission info and role.
     */
    private function transformReferral($referral)
    {
        if ($referral == null) {
            return null;
        }

        return [
            'id' => $referral->referals?->id,
            'user_id' => $referral->id,
            'name' => $referral->name,
            'email' => $referral->email,
            'phone_number' => $referral->phone_number,
            'address' => $referral->address,
            'commission' => $referral->referals?->commission,
            'role' => $referral->role?->name,
        ];
    }

    /**
     * Store a new invoice record with all related data.
     * Handles validation, creation of invoice and related records like tests, cultures, packages etc.
     */
    public function store(Request $request)
    {

        $user = Auth::user();
        if (! $user->hasPermissionTo(permission: 'invoices create')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        // validation
        $request->validate([
            'patient_id_fk' => 'required|integer',
            'referral_id_fk' => 'nullable|integer',
            'sub_total' => 'nullable|integer',
            'paid' => 'nullable|integer',
            'total' => 'nullable|integer',
            'notes' => 'nullable|string',
            'is_printed' => 'nullable|boolean',
            'attachments' => 'nullable|array',
            'from_lab_id_fk' => 'nullable|integer|exists:users,id',
            'contract_id_fk' => 'nullable|integer|exists:contracts,id',
            'sample_collector_id_fk' => 'nullable|integer|exists:users,id',
            'discount_type_id_fk' => 'nullable|integer',
            'discount' => 'nullable|integer',
            'show_result_date' => 'nullable|boolean',
            'show_patient_card_id' => 'nullable|boolean',
            'show_patient_pic' => 'nullable|boolean',
            'questions' => 'nullable|array',
            'tests' => 'nullable|array',
            'cultures' => 'nullable|array',
            'packages' => 'nullable|array',
            'test_groups' => 'nullable|array',
        ]);
        try {
            DB::beginTransaction();
            $invoice = new Invoice;
            // Frontend dropdown now sends users.id directly (optionValue="user_id").
            // referral_id_fk column is FK → users.id, so no remap needed.
            $invoice->patient_id_fk = $request->patient_id_fk;
            $invoice->referral_id_fk = $request->referral_id_fk ?: null;
            $invoice->sub_total = $request->sub_total;
            $invoice->total = $request->total;
            // Derive paid from payment_details when present (single source of truth — same logic as update())
            $invoice->paid = $request->payment_details
                ? collect($request->payment_details)->sum('amount')
                : ($request->paid ?? 0);
            $invoice->is_printed = $request->is_printed;
            $invoice->barcode = Invoice::generateUniqueBarcode();
            $invoice->notes = $request->notes;
            $invoice->from_lab_id_fk = $request->from_lab_id_fk;
            $invoice->lab_id_fk = Auth::user()->id;
            $invoice->contract_id_fk = $request->contract_id_fk;
            $invoice->sample_collector_id_fk = $request->sample_collector_id_fk;
            $invoice->discount_type_id_fk = $request->discount_type_id_fk ?: null;
            $invoice->discount = $request->discount;
            $invoice->show_result_date = $request->show_result_date ?? false;
            $invoice->show_patient_card_id = $request->show_patient_card_id ?? false;
            $invoice->show_patient_pic = $request->show_patient_pic ?? false;
            $invoice->save();

            // add invoice_test_rels
            if (isset($request->tests) && $request->tests !== null) {
                $tests = [];

                foreach ($request->input('tests') as $test) {
                    $tests[] = [
                        'invoice_id_fk' => $invoice->id,
                        'test_id_fk' => $test['test_id_fk'] ?? null,
                        'price' => $test['price'] ?? null,
                        'to_lab_id_fk' => $test['to_lab_id_fk'] ?? null,
                        'is_sample_received' => $test['is_sample_received'] ?? false,
                        'questions' => $test['questions'] ?? null,
                        'result' => $test['result'] ?? null,
                        'last_result' => $test['last_result'] ?? null,
                        'last_result_data' => $test['last_result_data'] ?? null,
                        'is_done' => $test['is_done'] ?? false,
                        'comment' => $test['comment'] ?? null,
                        'is_special_test' => $test['is_special_test'] ?? false,
                        'content' => $test['content'] ?? null,
                        'sub_tests' => $test['sub_tests'] ?? null,
                        'result_status_id_fk' => $test['result_status_id_fk'] ?? null,
                    ];
                }

                $invoice->invoiceTestRels()->createMany($tests);

            }
            // add invoice_test_rels
            if (isset($request->cultures) && $request->cultures !== null) {
                $cultures = [];
                foreach ($request->input('cultures') as $culture) {
                    $cultures[] = [
                        'invoice_id_fk' => $invoice->id,
                        'culture_id_fk' => $culture['culture_id_fk'],
                        'price' => $culture['price'] ?? null,
                        'to_lab_id_fk' => $culture['to_lab_id_fk'] ?? null,
                        'is_sample_received' => $culture['is_sample_received'] ?? false,
                        'questions' => $culture['questions'] ?? null,
                        'attribute' => $culture['attribute'] ?? null,
                        'result' => $culture['result'] ?? null,
                        'last_result' => $culture['last_result'] ?? null,
                        'last_result_data' => $culture['last_result_data'] ?? null,
                        'is_done' => $culture['is_done'] ?? false,
                        'comment' => $culture['comment'] ?? null,
                        'result_status_id_fk' => $culture['result_status_id_fk'] ?? null,
                    ];
                }
                $invoice->invoiceTestRels()->createMany($cultures);
            }
            // add invoice_test_rels
            if (isset($request->packages) && $request->packages !== null) {
                $packages = is_string($request->packages) ? json_decode($request->packages, true) : $request->packages;

                foreach ($packages as $package) {
                    // Safely access arrays with null coalescing operator
                    $packageTests = empty($package['tests']) ? null : $package['tests'];
                    $packageCultures = empty($package['cultures']) ? null : $package['cultures'];

                    // Convert to array if string
                    if (is_string($packageTests)) {
                        $packageTests = json_decode($packageTests, true) ?? [];
                    }
                    if (is_string($packageCultures)) {
                        $packageCultures = json_decode($packageCultures, true) ?? [];
                    }

                    InvoiceTestRel::create([
                        'invoice_id_fk' => $invoice->id,
                        'package_id_fk' => $package['package_id_fk'] ?? null,
                        'price' => $package['price'] ?? null,
                        'to_lab_id_fk' => $package['to_lab_id_fk'] ?? null,
                        'is_sample_received' => $package['is_sample_received'] ?? false,
                        'questions' => $package['questions'] ?? null,
                        'result' => $package['result'] ?? null,
                        'is_done' => $package['is_done'] ?? false,
                        'comment' => $package['comment'] ?? null,
                        'last_result' => $package['last_result'] ?? null,
                        'attribute' => $package['attribute'] ?? null,
                        'last_result_data' => $package['last_result_data'] ?? null,
                        'result_status_id_fk' => $package['status_id_fk'] ?? null,
                        'package_tests' => $packageTests ? json_encode($packageTests) : null,
                        'package_cultures' => $packageCultures ? json_encode($packageCultures) : null,

                    ]);
                }
            }

            if (isset($request->test_groups) && $request->test_groups !== null) {
                $test_groups = is_string($request->test_groups) ? json_decode($request->test_groups, true) : $request->test_groups;

                foreach ($test_groups as $test_group) {
                    // Safely access arrays with null coalescing operator
                    $test_group_tests = empty($test_group['tests']) ? null : $test_group['tests'];
                    $test_group_cultures = empty($test_group['culture']) ? null : $test_group['culture'];

                    // Convert to array if string
                    if (is_string($test_group_tests)) {
                        $test_group_tests = json_decode($test_group_tests, true) ?? [];
                    }
                    if (is_string($test_group_cultures)) {
                        $test_group_cultures = json_decode($test_group_cultures, true) ?? [];
                    }

                    InvoiceTestRel::create([
                        'invoice_id_fk' => $invoice->id,
                        'test_group_id_fk' => $test_group['test_group_id_fk'] ?? null,
                        'price' => $test_group['price'] ?? null,
                        'to_lab_id_fk' => $test_group['to_lab_id_fk'] ?? null,
                        'is_sample_received' => $test_group['is_sample_received'] ?? false,
                        'questions' => $test_group['questions'] ?? null,
                        'result' => $test_group['result'] ?? null,
                        'last_result' => $test_group['last_result'] ?? null,
                        'is_done' => $test_group['is_done'] ?? false,
                        'comment' => $test_group['comment'] ?? null,
                        'result_status_id_fk' => $test_group['result_status_id_fk'] ?? null,
                        'test_group_tests' => ! empty($test_group_tests) ? json_encode($test_group_tests) : null,
                        'test_group_cultures' => ! empty($test_group_cultures) ? json_encode($test_group_cultures) : null,

                    ]);
                }
            }

            // add invoice_paid_details
            if (isset($request->payment_details) && $request->payment_details !== null) {
                if (count($request->payment_details) < 2) {
                    foreach ($request->payment_details as $paid) {
                        InvoicePaidDetail::create([
                            'invoice_id_fk' => $invoice->id,
                            'lab_id_fk' => Auth::user()->id,
                            'amount' => $paid['amount'],
                            'contract_id_fk' => $paid['contract_id_fk'] ?? null,
                            'payment_method_id_fk' => $paid['payment_method_id_fk'],
                        ]);
                    }
                } else {
                    $lab_id = Auth::user()->id;
                    $payment_details = collect($request->payment_details)->map(function ($detail) use ($lab_id) {
                        $detail['lab_id_fk'] = $lab_id;

                        return $detail;
                    });
                    $invoice->paidDetails()->createMany($payment_details);
                }
            }

            ActivityLogController::storeActivity('إنشاء فاتورة', $invoice);
            $invoice->load($this->invoiceRelations());
            $invoiceJson = $this->transformInvoice($invoice);
            DB::commit();

            return response()->json($invoiceJson);
        } catch (\\Illuminate\\Validation\\ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (Exception $e) {
            DB::rollBack();
            ActivityLogController::errorActivity('خطأ في إنشاء فاتورة'.$e->getMessage());

            return response()->json(['message' => 'Failed to create invoice', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Save a PDF document for an invoice.
     * Handles file upload, storage and QR code generation.
     */
    public function savePdf(Request $request)
    {
        $invoice = Invoice::find($request->id);
        if (! $invoice) {
            return response()->json(['message' => 'invoice not found'], 404);
        }

        // Tenant check
        $user = Auth::user();
        if ($user->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (! in_array($invoice->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        if ($request->hasFile('result_doc')) {
            $last = explode('storage/', $invoice->result_doc);
            if (count($last) > 1) {
                $url = 'public/'.$last[1]; //  Storage::url($last[1]);
                if ($invoice->result_doc && Storage::exists($url)) {
                    Storage::delete($url);
                }
            }
            $path = $request->file('result_doc')->store('invoices', 'public');
            $invoice->result_doc = config('app.url').Storage::url($path);
            $qr_code = QrCode::format('png')->generate($invoice->result_doc);
            $qrFilename = 'qr_'.$invoice->id.'_'.time().'.png';
            Storage::disk('public')->put('invoices/'.$qrFilename, $qr_code);
            $qr_path = config('app.url').Storage::url('invoices/'.$qrFilename);
            $invoice->pdf_qr_code = $qr_path;
            $invoice->save();

            return response()->json([
                'message' => 'Invoice PDF saved successfully',
                'path' => $invoice->result_doc,
            ]);
        } else {
            return response()->json(['message' => 'No File To Save'], 404);
        }

    }

    /**
     * Toggle the signed status of an invoice.
     * Updates the signed status and signed by user.
     */
    public function signInvoice(Request $request)
    {
        $user = Auth::user();
        if (! $user->hasPermissionTo(permission: 'medical reports signiture')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $invoice = Invoice::find($request->id);
        if (! $invoice) {
            return response()->json(['message' => 'invoice not found'], 404);
        }

        // Tenant check
        if ($user->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (! in_array($invoice->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }
        if ($invoice->is_signed) {
            $invoice->is_signed = false;
            $invoice->signed_by_id_fk = null;
        } else {
            $invoice->is_signed = true;
            $invoice->signed_by_id_fk = Auth::user()->id;
        }

        $invoice->save();

        return response()->json(['message' => $invoice->is_signed ? 'invoice signed' : 'invoice unsigned']);

    }

    /**
     * Mark an invoice as sent to patient.
     * Updates the sent status flag.
     */
    public function sendInvoice(Request $request)
    {
        $user = Auth::user();
        if (! $user->hasPermissionTo(permission: 'invoices send whatsapp')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $invoice = Invoice::find($request->id);
        if (! $invoice) {
            return response()->json(['message' => 'invoice not found'], 404);
        }

        // Tenant check
        if ($user->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (! in_array($invoice->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $invoice->sent_to_patient = true;
        $invoice->public_with_background = $request->boolean('with_background', true);

        $invoice->save();

        return response()->json(['message' => 'invoice sent']);

    }

    /**
     * Download an invoice PDF document.
     * Retrieves and streams the stored PDF file.
     */
    public function downloadInvoice(Request $request)
    {
        $invoice = Invoice::find($request->id);

        if (! $invoice) {
            return response()->json(['message' => 'Invoice not found.'], 404);
        }

        // Tenant check
        $user = Auth::user();
        if ($user->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (! in_array($invoice->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $path = $invoice->result_doc;

        if ($path && Storage::exists($path)) {
            return Storage::download($path);
        }

        return response()->json(['message' => 'Invoice file not found.'], 404);
    }

    /**
     * Search for patients by name.
     * Returns matching patient records with user details.
     */
    public function searchByName(Request $request)
    {
        $searchTerm = $request->name;

        // Get the authenticated user for multi-tenant filtering
        $authUser = Auth::user();

        $query = User::with('patient.title', 'patient.gender', 'patient.ageUnit', 'role')
            ->where('role_id', 3)
            ->where(function ($q) use ($searchTerm) {
                // Case-insensitive search on name, phone, and patient code
                $q->whereLike('name', '%'.$searchTerm.'%')
                    ->orWhereLike('phone_number', '%'.$searchTerm.'%')
                    ->orWhereHas('patient', function ($subQ) use ($searchTerm) {
                        $subQ->whereLike('code', '%'.$searchTerm.'%');
                    });
            });

        // Multi-tenant filtering: Non-admin users only see their own lab's patients
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            $query->whereIn('creator_id', $users_ids);
        }

        $users = $query->orderBy('created_at', 'desc')->limit(20)->get();

        // Return empty array if no users found (not 404)
        if ($users->isEmpty()) {
            return response()->json([]);
        }

        $users = $users->map(function ($user) {
            return [
                'id' => $user->patient?->id,
                'name' => $user->name,
                'code' => $user->patient?->code,
                'email' => $user->email,
                'dob' => $user->patient?->dob,
                'passport_no' => $user->patient?->passport_no,
                'national_id_no' => $user->patient?->national_id_no,
                'image' => $user->image,
                'title' => $user->patient?->title?->title,
                'title_id_fk' => $user->patient?->title?->id,
                'age' => $user->patient?->age,
                'age_unit_id_fk' => $user->patient?->age_unit_id_fk,
                'gender' => $user->patient?->gender?->gender_type,
                'gender_type_id_fk' => $user->patient?->gender?->id,
                'phone' => $user->phone_number,
                'address' => $user->patient?->address,
                'role' => $user->role->name,
            ];
        });

        return response()->json($users);
    }

    /**
     * Search for patients by phone number.
     * Returns matching patient records with user details.
     */
    public function searchByPhone(Request $request)
    {
        // Get the authenticated user for multi-tenant filtering
        $authUser = Auth::user();

        $query = User::with('patient')
            ->whereLike('phone_number', '%'.$request->phone_number.'%')
            ->where('role_id', 3);

        // Multi-tenant filtering: Non-admin users only see their own lab's patients
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            $query->whereIn('creator_id', $users_ids);
        }

        $users = $query->get();

        // Return empty array if no users found (not 404)
        if ($users->isEmpty()) {
            return response()->json([]);
        }

        $users = $users->map(function ($user) {
            return [
                'id' => $user->patient?->id,
                'name' => $user->name,
                'code' => $user->patient?->code,
                'email' => $user->email,
                'dob' => $user->patient?->dob,
                'passport_no' => $user->patient?->passport_no,
                'national_id_no' => $user->patient?->national_id_no,
                'image' => $user->image,
                'title' => $user->patient?->title?->title,
                'title_id_fk' => $user->patient?->title?->id,
                'age' => $user->patient?->age,
                'age_unit_id_fk' => $user->patient?->age_unit_id_fk,
                'gender' => $user->patient?->gender?->gender_type,
                'gender_type_id_fk' => $user->patient?->gender?->id,
                'phone' => $user->phone_number,
                'address' => $user->patient?->address,
                'role' => $user->role?->name,
            ];
        });

        return response()->json($users);
    }

    /**
     * Search for patients by code.
     * Returns matching patient records with user details.
     */
    public function searchByCode(Request $request)
    {
        // Get the authenticated user for multi-tenant filtering
        $authUser = Auth::user();

        $query = Patient::with('user')
            ->whereLike('code', '%'.$request->code.'%');

        // Multi-tenant filtering: Non-admin users only see their own lab's patients
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            $query->whereHas('user', function ($q) use ($users_ids) {
                $q->whereIn('creator_id', $users_ids);
            });
        }

        $patients = $query->get();

        // Return empty array if no patients found (not 404)
        if ($patients->isEmpty()) {
            return response()->json([]);
        }

        $patients = $patients->map(function ($patient) {
            return [
                'id' => $patient->id,
                'name' => $patient->user?->name,
                'code' => $patient->code,
                'dob' => $patient->dob,
                'passport_no' => $patient->passport_no,
                'national_id_no' => $patient->national_id_no,
                'image' => $patient->user?->image,
                'title' => $patient->title?->title,
                'title_id_fk' => $patient->title_id_fk,
                'age' => $patient->age,
                'age_unit_id_fk' => $patient->age_unit_id_fk,
                'gender' => $patient->gender?->gender_type,
                'gender_type_id_fk' => $patient->gender?->id,
                'email' => $patient->user?->email,
                'phone' => $patient->user?->phone_number,
                'address' => $patient->user?->address,
                'role' => $patient->user?->role?->name,
            ];
        });

        return response()->json($patients);
    }

    /**
     * Get a specific invoice by ID.
     * Returns the invoice with all related data.
     */
    public function show(Request $request, $id)
    {
        $invoice = Invoice::with($this->invoiceRelations())->findOrFail($id);

        $authUser = Auth::user();
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (! in_array($invoice->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $invoiceJson = $this->transformInvoice($invoice);

        return response()->json($invoiceJson);
    }

    /**
     * Public endpoint for viewing invoice results (e.g. WhatsApp link).
     * Returns test results without financial data.
     */
    public function publicShow($id)
    {
        $invoice = Invoice::with($this->invoiceRelations())->find($id);
        if (! $invoice) {
            return response()->json(['message' => 'Invoice not found'], 404);
        }

        if (! $invoice->is_done) {
            return response()->json([
                'message' => 'Report is not completed yet',
                'status' => 'pending',
            ], 403);
        }

        $invoiceJson = $this->transformInvoice($invoice);

        // Strip financial data for public access
        unset(
            $invoiceJson['sub_total'],
            $invoiceJson['total'],
            $invoiceJson['paid'],
            $invoiceJson['discount'],
            $invoiceJson['discount_type'],
            $invoiceJson['discount_type_id_fk'],
            $invoiceJson['paidDetails'],
        );

        // Strip price from individual items
        if (! empty($invoiceJson['tests'])) {
            $invoiceJson['tests'] = array_map(function ($t) {
                unset($t['price'], $t['prices']);

                return $t;
            }, $invoiceJson['tests']);
        }
        if (! empty($invoiceJson['cultures'])) {
            $invoiceJson['cultures'] = array_map(function ($c) {
                unset($c['price'], $c['prices']);

                return $c;
            }, $invoiceJson['cultures']);
        }
        if (! empty($invoiceJson['packages'])) {
            $invoiceJson['packages'] = array_map(function ($p) {
                unset($p['price']);

                return $p;
            }, $invoiceJson['packages']);
        }
        if (! empty($invoiceJson['test_groups'])) {
            $invoiceJson['test_groups'] = array_map(function ($g) {
                unset($g['price']);

                return $g;
            }, $invoiceJson['test_groups']);
        }

        return response()->json($invoiceJson);
    }

    /**
     * Get test samples for an invoice.
     * Returns grouped test and culture samples.
     */
    public function getTestsSamples($id)
    {
        // Tenant check
        $invoice = Invoice::find($id);
        if (! $invoice) {
            return response()->json(['message' => 'Invoice not found'], 404);
        }
        $user = Auth::user();
        if ($user->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (! in_array($invoice->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $invoiceTestRels = InvoiceTestRel::where('invoice_id_fk', $id)->with(['test.sample', 'culture.sample', 'testGroup.tests.sample', 'testGroup.culture.sample', 'package.tests.sample', 'package.cultures.sample'])->get();
        if ($invoiceTestRels->isEmpty()) {
            return response()->json([]);
        }

        $tests = [];
        $cultures = [];
        foreach ($invoiceTestRels as $rel) {

            if ($rel->test?->sample) {
                $tests[] = [
                    'test_id_fk' => $rel->test?->id,
                    'name' => $rel->test?->name,
                    'sample_name' => $rel->test?->sample?->sample_name,
                ];
            }
            if ($rel->culture?->sample) {
                $cultures[] = [
                    'culture_id_fk' => $rel->culture?->id,
                    'name' => $rel->culture?->name,
                    'sample_name' => $rel->culture?->sample?->sample_name,
                ];
            }
            if ($rel->testGroup) {
                $test_group_tests = $rel->test_group_tests ? (is_string($rel->test_group_tests) ? json_decode($rel->test_group_tests, true) : $rel->test_group_tests) : $rel->testGroup?->tests;
                if ($test_group_tests) {
                    foreach ($test_group_tests as $test) {
                        $testObj = is_array($test) ? (object) $test : $test;
                        $sampleName = null;
                        if (isset($testObj->sample) && is_object($testObj->sample)) {
                            $sampleName = $testObj->sample->sample_name ?? null;
                        } elseif (is_array($test) && isset($test['sample']['sample_name'])) {
                            $sampleName = $test['sample']['sample_name'];
                        } elseif (is_array($test) && isset($test['sample_name'])) {
                            $sampleName = $test['sample_name'];
                        }
                        $tests[] = [
                            'test_id_fk' => is_array($test) ? ($test['test_id_fk'] ?? $test['id'] ?? null) : $testObj->id,
                            'name' => is_array($test) ? ($test['name'] ?? null) : $testObj->name,
                            'sample_name' => $sampleName,
                        ];
                    }
                }
                $test_group_cultures = $rel->test_group_cultures ? (is_string($rel->test_group_cultures) ? json_decode($rel->test_group_cultures, true) : $rel->test_group_cultures) : $rel->testGroup?->culture;
                if ($test_group_cultures) {
                    foreach ($test_group_cultures as $culture) {
                        $cultureObj = is_array($culture) ? (object) $culture : $culture;
                        $sampleName = null;
                        if (isset($cultureObj->sample) && is_object($cultureObj->sample)) {
                            $sampleName = $cultureObj->sample->sample_name ?? null;
                        } elseif (is_array($culture) && isset($culture['sample']['sample_name'])) {
                            $sampleName = $culture['sample']['sample_name'];
                        } elseif (is_array($culture) && isset($culture['sample_name'])) {
                            $sampleName = $culture['sample_name'];
                        }
                        $cultures[] = [
                            'culture_id_fk' => is_array($culture) ? ($culture['culture_id_fk'] ?? $culture['id'] ?? null) : $cultureObj->id,
                            'name' => is_array($culture) ? ($culture['name'] ?? null) : $cultureObj->name,
                            'sample_name' => $sampleName,
                        ];
                    }
                }
            }
            if ($rel->package) {
                $package_tests = $rel->package_tests ? (is_string($rel->package_tests) ? json_decode($rel->package_tests, true) : $rel->package_tests) : $rel->package?->tests;
                if ($package_tests) {
                    foreach ($package_tests as $test) {
                        $testObj = is_array($test) ? (object) $test : $test;
                        $sampleName = null;
                        if (isset($testObj->sample) && is_object($testObj->sample)) {
                            $sampleName = $testObj->sample->sample_name ?? null;
                        } elseif (is_array($test) && isset($test['sample']['sample_name'])) {
                            $sampleName = $test['sample']['sample_name'];
                        } elseif (is_array($test) && isset($test['sample_name'])) {
                            $sampleName = $test['sample_name'];
                        }
                        $tests[] = [
                            'test_id_fk' => is_array($test) ? ($test['test_id_fk'] ?? $test['id'] ?? null) : $testObj->id,
                            'name' => is_array($test) ? ($test['name'] ?? null) : $testObj->name,
                            'sample_name' => $sampleName,
                        ];
                    }
                }
                $package_cultures = $rel->package_cultures ? (is_string($rel->package_cultures) ? json_decode($rel->package_cultures, true) : $rel->package_cultures) : $rel->package?->cultures;
                if ($package_cultures) {
                    foreach ($package_cultures as $culture) {
                        $cultureObj = is_array($culture) ? (object) $culture : $culture;
                        $sampleName = null;
                        if (isset($cultureObj->sample) && is_object($cultureObj->sample)) {
                            $sampleName = $cultureObj->sample->sample_name ?? null;
                        } elseif (is_array($culture) && isset($culture['sample']['sample_name'])) {
                            $sampleName = $culture['sample']['sample_name'];
                        } elseif (is_array($culture) && isset($culture['sample_name'])) {
                            $sampleName = $culture['sample_name'];
                        }
                        $cultures[] = [
                            'culture_id_fk' => is_array($culture) ? ($culture['culture_id_fk'] ?? $culture['id'] ?? null) : $cultureObj->id,
                            'name' => is_array($culture) ? ($culture['name'] ?? null) : $cultureObj->name,
                            'sample_name' => $sampleName,
                        ];
                    }
                }
            }
        }

        $sample_names = [];
        foreach ($tests as $test) {
            $sample_names[] = is_object($test['sample_name']) ? $test['sample_name']->sample_name : $test['sample_name'];
        }
        foreach ($cultures as $culture) {
            $sample_names[] = is_object($culture['sample_name']) ? $culture['sample_name']->sample_name : $culture['sample_name'];
        }
        $sample_names = array_unique($sample_names);
        $sample_json = [];
        foreach ($sample_names as $sample) {
            $sample_json[] = [
                'sample_name' => $sample,
                'test' => collect($tests)->filter(fn ($test) => is_object($test['sample_name']) ? $test['sample_name']->sample_name == $sample : $test['sample_name'] == $sample),
                'culture' => collect($cultures)->filter(fn ($culture) => is_object($culture['sample_name']) ? $culture['sample_name']->sample_name == $sample : $culture['sample_name'] == $sample),
            ];
        }

        return response()->json(
            $sample_json,
        );
    }

    /**
     * Update test results for an invoice.
     * Handles updating test, culture and package results.
     */
    public function updateResult(Request $request)
    {

        $user = Auth::user();
        if (! $user->hasPermissionTo(permission: 'medical reports update')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        // Validation
        $request->validate([
            // 'id' => 'required|integer',
            'notes' => 'nullable|string',
            'attachments' => 'nullable|array',
            'attachments.*.name' => 'required|string',
            'attachments.*.file' => 'nullable|file|max:10240', // 10MB max
        ]);

        try {

            DB::beginTransaction();
            // Get the ID
            $id = $request->post('id');

            if (empty($id)) {
                return response()->json([
                    'message' => 'Invoice ID is required',
                ], 400);
            }

            // Convert JSON strings back to arrays for nested data
            $tests = is_string($request->tests) ? json_decode($request->tests, true) : $request->input('tests');
            $cultures = is_string($request->cultures) ? json_decode($request->cultures, true) : $request->input('cultures');
            $packages = is_string($request->packages) ? json_decode($request->packages, true) : $request->input('packages');
            $test_groups = is_string($request->test_groups) ? json_decode($request->test_groups, true) : $request->input('test_groups');

            $invoice = Invoice::find($id);
            if (! $invoice) {
                return response()->json(['message' => 'Invoice not found'], 404);
            }

            // Handle attachments
            $attachmentsArray = [];

            // Keep existing attachments that frontend sent back
            if ($request->has('existing_attachments')) {
                $kept = is_string($request->existing_attachments)
                    ? json_decode($request->existing_attachments, true)
                    : $request->existing_attachments;
                if (is_array($kept)) {
                    $attachmentsArray = array_merge($attachmentsArray, $kept);
                }
            }

            // Handle new file uploads
            if ($request->has('attachments')) {
                foreach ($request->attachments as $attachment) {
                    if (isset($attachment['file']) && $attachment['file'] instanceof UploadedFile) {
                        $file = $attachment['file'];
                        $originalName = $attachment['name'] ?? $file->getClientOriginalName();
                        $extension = $file->getClientOriginalExtension();
                        $filename = $originalName.'_'.time().'_'.uniqid().'.'.$extension;
                        $path = $file->storeAs('invoices/attachments', $filename, 'public');

                        $attachmentsArray[] = [
                            'name' => $originalName,
                            'file' => config('app.url').Storage::url($path),
                        ];
                    }
                }
            }

            $invoice->attachments = ! empty($attachmentsArray) ? $attachmentsArray : null;

            // Update other fields
            // $invoice->tests_comment = $request->input('tests_comment') === 'null' ? null : $request->input('tests_comment');
            // $invoice->cultures_comment = $request->input('cultures_comment') === 'null' ? null : $request->input('cultures_comment');

            $is_done = true;
            // Handle tests
            // $invoice->invoiceTestRels()->delete();
            if ($tests) {
                foreach ($tests as $test) {
                    if (! (isset($test['is_done']) && $test['is_done'])) {
                        $is_done = false;
                    }
                    InvoiceTestRel::upsert(
                        [
                            'invoice_id_fk' => $invoice->id,
                            'test_id_fk' => $test['test_id_fk'] ?? null,
                            'price' => $test['price'] ?? null,
                            'to_lab_id_fk' => ($test['to_lab_id_fk'] ?? null) ?: null,
                            'is_sample_received' => $test['is_sample_received'] ?? false,
                            'questions' => ! empty($test['questions']) ? (is_array($test['questions']) ? json_encode($test['questions']) : $test['questions']) : null,
                            'result' => $test['result'] ?? null,
                            'content' => ! empty($test['content']) ? (is_array($test['content']) ? json_encode($test['content']) : $test['content']) : null,
                            'sub_tests' => ! empty($test['sub_tests']) ? (is_array($test['sub_tests']) ? json_encode($test['sub_tests']) : $test['sub_tests']) : null,
                            'is_special_test' => $test['is_special_test'] ?? false,
                            'last_result' => $test['last_result'] ?? null,
                            'last_result_data' => ! empty($test['last_result_data']) ? (is_array($test['last_result_data']) ? json_encode($test['last_result_data']) : $test['last_result_data']) : null,
                            'is_done' => $test['is_done'] ?? false,
                            'comment' => $test['comment'] ?? null,
                            'result_status_id_fk' => ($test['result_status_id_fk'] ?? null) ?: null,
                            'result_status_text' => $test['result_status_text'] ?? null,
                        ],
                        ['invoice_id_fk', 'test_id_fk']
                    );
                }
            }

            // Handle cultures
            if ($cultures) {
                // $invoice->invoiceTestRels()->where('culture_id_fk', '!=', null)->delete();
                foreach ($cultures as $culture) {
                    if (! (isset($culture['is_done']) && $culture['is_done'])) {
                        $is_done = false;
                    }
                    InvoiceTestRel::upsert(
                        [
                            'invoice_id_fk' => $invoice->id,
                            'culture_id_fk' => $culture['culture_id_fk'],
                            'price' => $culture['price'] ?? null,
                            'to_lab_id_fk' => ($culture['to_lab_id_fk'] ?? null) ?: null,
                            'is_sample_received' => $culture['is_sample_received'] ?? false,
                            'questions' => ! empty($culture['questions']) ? (is_array($culture['questions']) ? json_encode($culture['questions']) : $culture['questions']) : null,
                            'result' => $culture['result'] ?? null,
                            'last_result' => $culture['last_result'] ?? null,
                            'comment' => $culture['comment'] ?? null,
                            'is_done' => $culture['is_done'] ?? false,
                            'result_status_id_fk' => ($culture['result_status_id_fk'] ?? null) ?: null,
                            'created_at' => $culture['created_at'] ?? null,
                            'updated_at' => $culture['updated_at'] ?? null,
                        ],
                        ['invoice_id_fk', 'culture_id_fk']
                    );
                }
            }

            // Handle packages
            if ($packages) {
                foreach ($packages as $package) {
                    // Initialize package_is_done as true only if there are tests or cultures
                    $package_is_done = (! empty($package['tests']) || ! empty($package['cultures']));

                    // Check tests
                    if (! empty($package['tests'])) {
                        foreach ($package['tests'] as $test) {
                            // If any test is not done, mark package as not done
                            if (! (isset($test['is_done']) && $test['is_done'])) {
                                $package_is_done = false;
                                break; // No need to check further tests
                            }
                        }
                    }

                    // Only check cultures if package is still marked as done
                    if ($package_is_done && ! empty($package['cultures'])) {
                        foreach ($package['cultures'] as $culture) {
                            // If any culture is not done, mark package as not done
                            if (! (isset($culture['is_done']) && $culture['is_done'])) {
                                $package_is_done = false;
                                $is_done = false;
                                break; // No need to check further cultures
                            }
                        }
                    }

                    // If package has no tests and no cultures, it should be marked as not done
                    if (empty($package['tests']) && empty($package['cultures'])) {
                        $package_is_done = false;
                    }

                    InvoiceTestRel::upsert(
                        [
                            'invoice_id_fk' => $invoice->id,
                            'package_id_fk' => $package['package_id_fk'] ?? null,
                            'price' => $package['price'] ?? null,
                            'to_lab_id_fk' => ($package['to_lab_id_fk'] ?? null) ?: null,
                            'is_sample_received' => $package['is_sample_received'] ?? false,
                            'questions' => ! empty($package['questions']) ? (is_array($package['questions']) ? json_encode($package['questions']) : $package['questions']) : null,
                            'result' => $package['result'] ?? null,
                            'last_result' => $package['last_result'] ?? null,
                            'is_done' => $package_is_done,
                            'comment' => $package['comment'] ?? null,
                            'result_status_id_fk' => ($package['result_status_id_fk'] ?? null) ?: null,
                            'package_tests' => ! empty($package['tests']) ? json_encode($package['tests']) : null,
                            'package_cultures' => ! empty($package['cultures']) ? json_encode($package['cultures']) : null,

                        ],
                        ['invoice_id_fk', 'package_id_fk']
                    );
                }
            }
            if ($test_groups) {
                foreach ($test_groups as $test_group) {
                    // Initialize package_is_done as true only if there are tests or cultures
                    $test_group_is_done = (! empty($test_group['tests']) || ! empty($test_group['cultures']));

                    // Check tests
                    if (! empty($test_group['tests'])) {
                        foreach ($test_group['tests'] as $test) {
                            // If any test is not done, mark package as not done
                            if (! (isset($test['is_done']) && $test['is_done'])) {
                                $test_group_is_done = false;
                                break; // No need to check further tests
                            }
                        }
                    }

                    // Only check cultures if package is still marked as done
                    if ($test_group_is_done && ! empty($test_group['cultures'])) {
                        foreach ($test_group['cultures'] as $culture) {
                            // If any culture is not done, mark package as not done
                            if (! (isset($culture['is_done']) && $culture['is_done'])) {
                                $test_group_is_done = false;
                                $is_done = false;
                                break; // No need to check further cultures
                            }
                        }
                    }

                    // If package has no tests and no cultures, it should be marked as not done
                    if (empty($test_group['tests']) && empty($test_group['cultures'])) {
                        $test_group_is_done = false;
                    }

                    InvoiceTestRel::upsert(
                        [
                            'invoice_id_fk' => $invoice->id,
                            'test_group_id_fk' => $test_group['test_group_id_fk'] ?? null,
                            'price' => $test_group['price'] ?? null,
                            'to_lab_id_fk' => ($test_group['to_lab_id_fk'] ?? null) ?: null,
                            'is_sample_received' => $test_group['is_sample_received'] ?? false,
                            'questions' => ! empty($test_group['questions']) ? (is_array($test_group['questions']) ? json_encode($test_group['questions']) : $test_group['questions']) : null,
                            'result' => $test_group['result'] ?? null,
                            'last_result' => $test_group['last_result'] ?? null,
                            'is_done' => $test_group_is_done,
                            'comment' => $test_group['comment'] ?? null,
                            'result_status_id_fk' => ($test_group['result_status_id_fk'] ?? null) ?: null,
                            'test_group_tests' => ! empty($test_group['tests']) ? json_encode($test_group['tests']) : null,
                            'test_group_cultures' => ! empty($test_group['cultures']) ? json_encode($test_group['cultures']) : null,

                        ],
                        ['invoice_id_fk', 'test_group_id_fk']
                    );
                }
            }
            $invoice->is_done = $is_done;
            $invoice->result_date = ($is_done && $invoice->result_date == null) ? now()->toDateTimeString() : $invoice->result_date;
            // $invoice->test_group_comment = isset($request->test_group_comment) ? (($request->input('test_group_comment') === 'null' ? null : $request->input('test_group_comment'))) : null;
            $invoice->tests_comment = $request->input('tests_comment') === 'null' ? null : $request->input('tests_comment');
            $invoice->cultures_comment = $request->input('cultures_comment') === 'null' ? null : $request->input('cultures_comment');
            $invoice->packages_comment = isset($request->package_comment) ? (($request->input('package_comment') === 'null' ? null : $request->input('package_comment'))) : null;
            $invoice->notes = $request->input('notes') === 'null' ? null : $request->input('notes');
            $invoice->save();

            // Upsert bypasses Eloquent observers, so reconcile all completed relations here.
            $inventory = app(\\App\\Services\\InventoryService::class);
            InvoiceTestRel::where('invoice_id_fk', $invoice->id)->get()->each(
                fn (InvoiceTestRel $relation) => $inventory->consumeInitial($relation)
            );

            $invoice->load($this->invoiceRelations());
            $invoiceJson = $this->transformInvoice($invoice);

            DB::commit();

            return response()->json($invoiceJson);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('updateResult failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'invoice_id' => $id ?? null,
            ]);
            ActivityLogController::errorActivity('خطأ في تعديل فاتورة'.$e->getMessage());

            return response()->json(['message' => 'Failed to update invoice', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @return JsonResponse
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        if (! $user->hasPermissionTo(permission: 'invoices edit')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        // validation
        $request->validate([
            'patient_id_fk' => 'required|integer',
            'referral_id_fk' => 'nullable|integer',
            'is_printed' => 'nullable|boolean',
            'attachments' => 'nullable|array',
            'notes' => 'nullable|string',
            'from_lab_id_fk' => 'nullable|integer',
            'contract_id_fk' => 'nullable|integer',
            'sample_collector_id_fk' => 'nullable|integer',
            'discount_type_id_fk' => 'nullable|integer',
            'discount' => 'nullable|integer',
            'show_result_date' => 'nullable|boolean',
            'show_patient_card_id' => 'nullable|boolean',
            'show_patient_pic' => 'nullable|boolean',
            'questions' => 'nullable|array',
            'tests' => 'nullable|array',
            'cultures' => 'nullable|array',
            'packages' => 'nullable|array',
            'test_groups' => 'nullable|array',
        ]);

        try {
            DB::beginTransaction();
            $invoice = Invoice::find($request->id);
            if (! $invoice) {
                return response()->json(['message' => 'Invoice not found'], 404);
            }

            // Tenant check: verify invoice belongs to user's lab
            $authUser = Auth::user();
            if ($authUser->role_id != 1) {
                $users_ids = $this->getTenantUserIds();
                if (! in_array($invoice->lab_id_fk, $users_ids)) {
                    return response()->json(['message' => 'Unauthorized'], 403);
                }
            }

            $oldInvoice = $invoice->replicate();
            // Frontend dropdown now sends users.id directly (optionValue="user_id").
            // referral_id_fk column is FK → users.id, so no remap needed.
            $invoice->patient_id_fk = $request->patient_id_fk;
            $invoice->referral_id_fk = $request->referral_id_fk ?: null;
            $invoice->sub_total = $request->sub_total;
            $invoice->total = $request->total;
            $invoice->paid = ($request->payment_details ? collect($request->payment_details)->sum('amount') : 0);
            $invoice->notes = $request->notes;
            $invoice->is_printed = $request->is_printed;
            $invoice->from_lab_id_fk = $request->from_lab_id_fk;
            $invoice->contract_id_fk = $request->contract_id_fk;
            $invoice->sample_collector_id_fk = $request->sample_collector_id_fk;
            $invoice->discount_type_id_fk = $request->discount_type_id_fk ?: null;
            $invoice->discount = $request->discount;
            $invoice->show_result_date = $request->show_result_date ?? false;
            $invoice->show_patient_card_id = $request->show_patient_card_id ?? false;
            $invoice->show_patient_pic = $request->show_patient_pic ?? false;
            $invoice->save();

            // Delete existing invoice test relations and recreate
            $invoice->invoiceTestRels()->delete();

            if (isset($request->tests) && $request->tests !== null) {
                $tests = [];
                foreach ($request->input('tests') as $test) {
                    $tests[] = [
                        'invoice_id_fk' => $invoice->id,
                        'test_id_fk' => $test['test_id_fk'] ?? null,
                        'price' => $test['price'] ?? null,
                        'to_lab_id_fk' => $test['to_lab_id_fk'] ?? null,
                        'is_sample_received' => $test['is_sample_received'] ?? false,
                        'questions' => $test['questions'] ?? null,
                        'result' => $test['result'] ?? null,
                        'last_result' => $test['last_result'] ?? null,
                        'is_done' => $test['is_done'] ?? false,
                        'comment' => $test['comment'] ?? null,
                        'result_status_id_fk' => $test['result_status_id_fk'] ?? null,
                    ];
                }
                $invoice->invoiceTestRels()->createMany($tests);
            }
            // add cultures
            if (isset($request->cultures) && $request->cultures !== null) {
                $cultures = [];
                foreach ($request->input('cultures') as $culture) {
                    $cultures[] = [
                        'invoice_id_fk' => $invoice->id,
                        'culture_id_fk' => $culture['culture_id_fk'],
                        'price' => $culture['price'] ?? null,
                        'to_lab_id_fk' => $culture['to_lab_id_fk'] ?? null,
                        'is_sample_received' => $culture['is_sample_received'] ?? false,
                        'questions' => $culture['questions'] ?? null,
                        'attribute' => $culture['attribute'] ?? null,
                        'result' => $culture['result'] ?? null,
                        'last_result' => $culture['last_result'] ?? null,
                        'comment' => $culture['comment'] ?? null,
                        'is_done' => $culture['is_done'] ?? false,
                        'result_status_id_fk' => $culture['result_status_id_fk'] ?? null,
                    ];
                }
                $invoice->invoiceTestRels()->createMany($cultures);
            }
            // add packages
            if (isset($request->packages) && $request->packages !== null) {
                $packagesData = is_string($request->packages) ? json_decode($request->packages, true) : $request->packages;
                $packagesToCreate = [];

                foreach ($packagesData as $package) {
                    $packageTests = $package['tests'] ?? $package['package_tests'] ?? null;
                    $packageCultures = $package['cultures'] ?? $package['package_cultures'] ?? null;

                    $packagesToCreate[] = [
                        'invoice_id_fk' => $invoice->id,
                        'package_id_fk' => $package['package_id_fk'] ?? null,
                        'price' => $package['price'] ?? null,
                        'to_lab_id_fk' => $package['to_lab_id_fk'] ?? null,
                        'is_sample_received' => $package['is_sample_received'] ?? false,
                        'questions' => $package['questions'] ?? null,
                        'result' => $package['result'] ?? null,
                        'is_done' => $package['is_done'] ?? false,
                        'comment' => $package['comment'] ?? null,
                        'last_result' => $package['last_result'] ?? null,
                        'attribute' => $package['attribute'] ?? null,
                        'last_result_data' => $package['last_result_data'] ?? null,
                        'result_status_id_fk' => $package['status_id_fk'] ?? $package['result_status_id_fk'] ?? null,
                        'package_tests' => ! empty($packageTests) ? (is_array($packageTests) ? json_encode($packageTests) : $packageTests) : null,
                        'package_cultures' => ! empty($packageCultures) ? (is_array($packageCultures) ? json_encode($packageCultures) : $packageCultures) : null,
                    ];
                }
                $invoice->invoiceTestRels()->createMany($packagesToCreate);
            }

            // add test_groups
            if (isset($request->test_groups) && $request->test_groups !== null) {
                $testGroupsData = is_string($request->test_groups) ? json_decode($request->test_groups, true) : $request->test_groups;
                $testGroupsToCreate = [];

                foreach ($testGroupsData as $test_group) {
                    $test_group_tests = $test_group['tests'] ?? $test_group['test_group_tests'] ?? null;
                    $test_group_cultures = $test_group['cultures'] ?? $test_group['culture'] ?? $test_group['test_group_cultures'] ?? null;

                    $testGroupsToCreate[] = [
                        'invoice_id_fk' => $invoice->id,
                        'test_group_id_fk' => $test_group['test_group_id_fk'] ?? null,
                        'price' => $test_group['price'] ?? null,
                        'to_lab_id_fk' => $test_group['to_lab_id_fk'] ?? null,
                        'is_sample_received' => $test_group['is_sample_received'] ?? false,
                        'questions' => $test_group['questions'] ?? null,
                        'result' => $test_group['result'] ?? null,
                        'last_result' => $test_group['last_result'] ?? null,
                        'is_done' => $test_group['is_done'] ?? false,
                        'comment' => $test_group['comment'] ?? null,
                        'result_status_id_fk' => $test_group['result_status_id_fk'] ?? null,
                        'test_group_tests' => ! empty($test_group_tests) ? (is_array($test_group_tests) ? json_encode($test_group_tests) : $test_group_tests) : null,
                        'test_group_cultures' => ! empty($test_group_cultures) ? (is_array($test_group_cultures) ? json_encode($test_group_cultures) : $test_group_cultures) : null,
                    ];
                }
                $invoice->invoiceTestRels()->createMany($testGroupsToCreate);
            }

            // add invoice_paid_details
            if (isset($request->payment_details) && $request->payment_details !== null) {
                // Delete existing paid details and recreate
                $invoice->paidDetails()->delete();

                $lab_id = Auth::user()->id;
                $paymentDetailsToCreate = [];
                foreach ($request->payment_details as $paid) {
                    $paymentDetailsToCreate[] = [
                        'invoice_id_fk' => $invoice->id,
                        'lab_id_fk' => $lab_id,
                        'amount' => $paid['amount'] ?? null,
                        'contract_id_fk' => $paid['contract_id_fk'] ?? null,
                        'payment_method_id_fk' => $paid['payment_method_id_fk'] ?? null,
                    ];
                }
                $invoice->paidDetails()->createMany($paymentDetailsToCreate);
            }

            ActivityLogController::updateActivity('تعديل فاتورة', $oldInvoice, $invoice);
            $invoice->load($this->invoiceRelations());
            $invoiceJson = $this->transformInvoice($invoice);
            DB::commit();

            return response()->json($invoiceJson);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Invoice update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'invoice_id' => $request->id,
            ]);
            ActivityLogController::errorActivity('خطأ في تعديل فاتورة'.$e->getMessage());

            return response()->json(['message' => 'Failed to update invoice', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * Add a payment to an existing invoice.
     */
    public function addPayment(Request $request)
    {
        $user = Auth::user();
        if (! $user->hasPermissionTo('invoices edit')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'invoice_id' => 'required|integer|exists:invoices,id',
            'amount' => 'required|numeric|min:1',
            'payment_method_id_fk' => 'required|integer|exists:payment_methods,id',
        ]);

        $invoice = Invoice::with('paidDetails')->find($validated['invoice_id']);

        // Tenant check
        if ($user->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (! in_array($invoice->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        // Check if overpaying
        $currentPaid = $invoice->paidDetails->sum('amount');
        $remaining = ($invoice->total ?? 0) - $currentPaid;
        if ($validated['amount'] > $remaining && $remaining > 0) {
            return response()->json(['message' => 'المبلغ أكبر من المتبقي ('.$remaining.')'], 422);
        }

        $payment = InvoicePaidDetail::create([
            'invoice_id_fk' => $invoice->id,
            'lab_id_fk' => $user->role_id == 2 ? $user->id : ($user->creator_id ?? $user->id),
            'amount' => $validated['amount'],
            'payment_method_id_fk' => $validated['payment_method_id_fk'],
        ]);

        // Refresh paid total
        $invoice->refresh();
        $newPaid = $invoice->paidDetails->sum('amount');

        ActivityLogController::storeActivity(
            'إضافة دفعة على فاتورة: '.$invoice->barcode.' - '.$validated['amount'],
            $payment
        );

        return response()->json([
            'message' => 'Payment added successfully',
            'payment' => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'payment_method' => $payment->paymentMethod?->name,
                'paid_at' => $payment->created_at,
            ],
            'total_paid' => $newPaid,
            'remaining' => ($invoice->total ?? 0) - $newPaid,
            'payment_status' => $this->getPaymentStatus($invoice),
        ]);
    }

    /**
     * @return JsonResponse
     */
    public function destroy(Request $request)
    {
        $user = Auth::user();
        if (! $user->hasPermissionTo(permission: 'invoices delete')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $invoice = Invoice::find($request->id);
        if (! $invoice) {
            return response()->json(['message' => 'invoice not found'], 404);
        }

        if ($user->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (! in_array($invoice->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $invoice->invoiceTestRels()->delete();
        $invoice->delete();
        ActivityLogController::deleteActivity('حذف فاتورة', $invoice);

        return response()->json(['message' => 'invoice deleted successfully']);
    }

    private function collectResultComments($rel, &$result_comments_tests, &$result_comments_cultures, &$result_package_comments, &$test_group_comments)
    {
        if ($rel->test?->result_comments != null) {
            $result_comments_tests = array_merge($result_comments_tests, $rel->test->result_comments);
        }
        if ($rel->culture?->result_comments != null) {
            $result_comments_cultures = array_merge($result_comments_cultures, $rel->culture->result_comments);
        }
        if ($rel->package?->tests != null) {
            foreach ($rel->package->tests as $test) {
                if ($test->result_comments != null) {
                    $result_package_comments = array_merge($result_package_comments, $test->result_comments);
                }
            }
        }
        if ($rel->package?->cultures != null) {
            foreach ($rel->package->cultures as $culture) {
                if ($culture->result_comments != null) {
                    $result_package_comments = array_merge($result_package_comments, $culture->result_comments);
                }
            }
        }
        if ($rel->testGroup?->result_comments != null) {
            $test_group_comments = array_merge($test_group_comments, $rel->testGroup->result_comments);
        }
    }
}
