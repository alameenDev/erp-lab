<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceTestRel;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportsController extends Controller
{
    public function getReports(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('reports view')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'report_type' => 'nullable|string|in:accounting,invoices,referrals,contracts,from_lab,to_lab,sample_collectors,quick_summary',
            'id' => 'nullable',
            'lab_id' => 'nullable',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();

        // SECURITY: Non-admin users must use their own lab_id
        $authUser = Auth::user();
        if ($authUser->role_id != 1) {
            $request->merge(['lab_id' => $authUser->id]);
        }

        switch ($request->report_type) {
            case 'accounting':
                return $this->getInvoicesReport($startDate, $endDate, $request->lab_id);
            case 'invoices':
                return $this->getInvoicesReport($startDate, $endDate, $request->lab_id);
            case 'contracts':
                return $this->getContractsReport($startDate, $endDate, $request->id, $request->lab_id);
            case 'from_lab':
                return $this->getFromLabReport($startDate, $endDate, $request->id, $request->lab_id);
            case 'to_lab':
                return $this->getToLabReport($startDate, $endDate, $request->id, $request->lab_id);
            case 'sample_collectors':
                return $this->getSampleCollectorsReport($startDate, $endDate, $request->id, $request->lab_id);
            case 'referrals':
                return $this->getReferralsReport($startDate, $endDate, $request->id, $request->lab_id);
            case 'quick_summary':
                return $this->getQuickSummaryReport($startDate, $endDate, $request->lab_id);
            default:
                return response()->json(['message' => 'Invalid report type'], 400);
        }

    }

    private function getInvoicesReport($startDate, $endDate, $lab_id)
    {
        // $cacheKey = ('invoice_report ' . "{$startDate}_{$endDate}");
        $alltests = [];
        $allcultures = [];
        $allpackages = [];
        $allcontracts = [];
        $allreferals = [];
        $allpatients = [];
        $allpayments = [];
        $alltestGroups = [];
        $paidAmount = 0;
        $originalPrice = 0;

        // Build query - if lab_id provided, filter by it; otherwise show all for admin
        $query = Invoice::where('is_done', 1);
        if ($lab_id) {
            $query->where('lab_id_fk', $lab_id);
        } elseif (Auth::user()->role_id != 1) {
            $query->where('lab_id_fk', Auth::user()->id);
        }
        $invoices = $query
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with([
                'patient.user:id,name',
                'paidDetails.paymentMethod:id,name',
                'contract:id,name,discount_percentage',
                'lab:id,name',
                'referral:id,name',
                'referral.referals:id,referral_id_fk,commission',
                'invoiceTestRels.test:id,name,price',
                'invoiceTestRels.culture:id,name,price',
                'invoiceTestRels.package:id,name,price,is_constant_price',
                'invoiceTestRels.package.tests',
                'invoiceTestRels.package.cultures',
                'invoiceTestRels.testGroup:id,group_name',
                'invoiceTestRels.testGroup.tests:id,test_group_id_fk,name,price',
                'invoiceTestRels.testGroup.culture:id,test_group_id_fk,name,price',
            ])
            ->orderBy('created_at', 'desc')
            ->get();
        $invoicesData = $invoices->map(function ($invoice) use (&$alltests, &$allcultures, &$allpackages, &$allcontracts, &$allreferals, &$allpatients, &$alltestGroups, &$allpayments, &$paidAmount, &$originalPrice) {

            $testsFromInvoices = [];
            $culturesFromInvoices = [];
            $packagesFromInvoices = [];
            $testGroupsFromInvoices = [];
            foreach ($invoice->invoiceTestRels as $invoiceTestRel) {
                if ($invoiceTestRel->test != null) {

                    $testsFromInvoices[] = [
                        'name' => $invoiceTestRel->test->name,
                        'price' => $invoiceTestRel->price,
                        'invoice_id' => $invoiceTestRel->invoice_id_fk,
                        'created_at' => $invoiceTestRel->created_at,
                    ];
                    $originalPrice += $invoiceTestRel->test->price;
                }
                if ($invoiceTestRel->culture != null) {

                    $culturesFromInvoices[] = [
                        'name' => $invoiceTestRel->culture->name,
                        'price' => $invoiceTestRel->price,
                        'invoice_id' => $invoiceTestRel->invoice_id_fk,
                        'created_at' => $invoiceTestRel->created_at,
                    ];
                    $originalPrice += $invoiceTestRel->culture->price;
                }
                if ($invoiceTestRel->package != null) {
                    $packagesFromInvoices[] = [
                        'name' => $invoiceTestRel->package->name,
                        'price' => $invoiceTestRel->price,
                        'invoice_id' => $invoiceTestRel->invoice_id_fk,
                        'created_at' => $invoiceTestRel->created_at,
                    ];

                    if ($invoiceTestRel->package->is_constant_price) {
                        $originalPrice += collect($invoiceTestRel->package->tests)->sum('price');
                        $originalPrice += collect($invoiceTestRel->package->cultures)->sum('price');
                    } else {
                        $originalPrice += $invoiceTestRel->package->price;
                    }

                }
                if ($invoiceTestRel->testGroup != null) {
                    $testGroupsFromInvoices[] = [
                        'name' => $invoiceTestRel->testGroup->group_name,
                        'price' => $invoiceTestRel->price,
                        'invoice_id' => $invoiceTestRel->invoice_id_fk,
                        'created_at' => $invoiceTestRel->created_at,
                    ];
                    $originalPrice += collect($invoiceTestRel->testGroup->tests)->sum('price');
                    $originalPrice += collect($invoiceTestRel->testGroup->culture)->sum('price');
                }
            }

            $contract = null;
            $referal = null;

            if ($invoice->contract != null) {

                $contract = [
                    'name' => $invoice->contract?->name,
                    'discount_percentage' => $invoice->contract?->discount_percentage,
                ];

            }
            if ($invoice->referral != null) {
                $referal = [
                    'name' => $invoice->referral?->name,
                    'commission' => $invoice->referral?->referals?->commission,
                ];

            }
            $alltests[] = $testsFromInvoices;
            $allcultures[] = $culturesFromInvoices;
            $allpackages[] = $packagesFromInvoices;
            $alltestGroups[] = $testGroupsFromInvoices;
            $allcontracts[] = $contract;
            $allreferals[] = $referal;
            $allPaidAmountForPatient = 0;
            $paidAmount += $invoice->paidDetails?->sum('amount') ?? 0;

            if ($invoice->patient) {
                $patientId = $invoice->patient->id;
                $allPaidAmountForPatient = $invoice->paidDetails?->sum('amount') ?? 0;
                if (!isset($allpatients[$patientId])) {
                    $allpatients[$patientId] = [
                        'id' => $invoice->patient->id,
                        'code' => $invoice->patient?->code,
                        'name' => $invoice->patient?->user?->name,
                        'total' => $invoice->total,
                        'patient_payment' => $invoice->paid,
                        'paid' => $allPaidAmountForPatient,
                        'due' => $invoice->total - $allPaidAmountForPatient,
                    ];
                } else {
                    $allpatients[$patientId]['total'] += $invoice->total;
                    $allpatients[$patientId]['patient_payment'] += $invoice->paid;
                    $allpatients[$patientId]['paid'] += $allPaidAmountForPatient;
                    $allpatients[$patientId]['due'] += ($invoice->total - $allPaidAmountForPatient);
                }
            }
            if ($invoice->paidDetails) {
                foreach ($invoice->paidDetails as $payment) {
                    if ($payment->paymentMethod) {
                        $allpayments[] = [
                            'name' => $payment->paymentMethod->name,
                            'amount' => $payment->amount,
                            'invoice_id' => $invoice->id,
                            'created_at' => $payment->created_at,
                        ];
                    }
                }
            }

            // if ($invoice->createdBy) {
            //     $allusers[] = [
            //         'name' => $invoice->createdBy->name,
            //         'invoice_id' => $invoice->id,
            //         'created_at' => $invoice->created_at
            //     ];
            // }

            return [
                'tests' => array_merge($testsFromInvoices, $culturesFromInvoices, $packagesFromInvoices, $testGroupsFromInvoices),
                'contract' => $contract,
                'referal' => $referal,
                'invoice_id' => $invoice->id,
                'patient_name' => $invoice->patient?->user?->name,
                'created_by' => $invoice->lab?->name,
                'subtotal' => $invoice->sub_total,
                'discount' => $invoice->discount,
                'total' => $invoice->total,
                'due' => $invoice->total - $allPaidAmountForPatient,
                'created_at' => $invoice->created_at,
            ];

        });
        $totalAmount = $invoices->sum('total');
        $discount = $invoices->sum('discount');
        $subtotal = $invoices->sum('sub_total');

        $due = ($totalAmount) - $paidAmount;
        $referalCommission = collect($allreferals)->sum('commission');
        $profit = $totalAmount - $discount - $referalCommission - $originalPrice;
        $lab = $lab_id ? User::find($lab_id) : null;

        return response()->json([
            'total_invoices' => $invoices->count(),
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'discount' => $discount,
            'subtotal' => $subtotal,
            'lab' => $lab?->name ?? 'All Labs',
            'due' => $due,
            'profit' => $profit,
            'referal_commission' => $referalCommission,
            'invoices' => $invoicesData,
            'tests' => collect($alltests)->flatten(1)
                ->filter()
                ->groupBy('name')
                ->map(function ($group) {
                    return [
                        'name' => $group->first()['name'] ?? 'Unknown',
                        'count' => $group->count(),
                        'total' => $group->sum('price'),
                    ];
                })->values(),
            'test_groups' => collect($alltestGroups)->flatten(1)
                ->filter()
                ->groupBy('name')
                ->map(function ($group) {
                    return [
                        'name' => $group->first()['name'] ?? 'Unknown',
                        'count' => $group->count(),
                        'total' => $group->sum('price'),
                    ];
                })->values(),
            'cultures' => collect($allcultures)->flatten(1)
                ->filter()
                ->groupBy('name')
                ->map(function ($group) {
                    return [
                        'name' => $group->first()['name'] ?? 'Unknown',
                        'count' => $group->count(),
                        'total' => $group->sum('price'),
                    ];
                })->values(),

            'packages' => collect($allpackages)->flatten(1)
                ->filter()
                ->groupBy('name')
                ->map(function ($group) {
                    return [
                        'name' => $group->first()['name'] ?? 'Unknown',
                        'count' => $group->count(),
                        'total' => $group->sum('price'),
                    ];
                })->values(),

            'contracts' => collect($allcontracts)
                ->filter()
                ->groupBy('name')
                ->map(function ($group) {
                    return [
                        'name' => $group->first()['name'] ?? 'Unknown',
                        'count' => $group->count(),
                        'discount_percentage' => $group->sum('discount_percentage'),
                    ];
                })->values(),

            'referals' => collect($allreferals)
                ->filter()
                ->groupBy('name')
                ->map(function ($group) use ($invoicesData) {
                    $firstReferal = $group->first();
                    $referalName = $firstReferal['name'] ?? 'Unknown';

                    // Filter invoices by referal name
                    $referalInvoicesTotal = $invoicesData
                        ->filter(fn($inv) => ($inv['referal']['name'] ?? null) === $referalName)
                        ->sum('total');

                    return [
                        'name' => $referalName,
                        'count' => $group->count(),
                        'total_invoices' => $referalInvoicesTotal,
                        'total' => $group->sum('commission'),
                        'profit' => $referalInvoicesTotal - $group->sum('commission'),
                    ];
                })->values(),
            'patients' => collect($allpatients)
                ->filter()
                ->values()
                ->groupBy('name')
                ->map(function ($group) {
                    $firstPatient = $group->first();

                    return [
                        'name' => $firstPatient['name'] ?? 'Unknown',
                        'count' => $group->count(),
                        'total_invoices' => $group->sum('total'),
                        'total_paid' => $group->sum('paid'),
                        'total_due' => $group->sum('due'),
                    ];
                })->values(),

            'payment_methods' => collect($allpayments)
                ->filter()
                ->groupBy('name')
                ->map(function ($group) {
                    return [
                        'name' => $group->first()['name'] ?? 'Unknown',
                        'count' => $group->count(),
                        'total' => $group->sum('amount'),
                    ];
                })->values(),

        ]);
    }

    private function getContractsReport($startDate, $endDate, $contract_id = null, $lab_id = null)
    {
        if (!$contract_id) {
            return response()->json(['message' => 'Contract ID is required'], 400);
        }
        $lab_id = $lab_id ?? Auth::user()->id;
        $contracts = Contract::whereHas('invoices', function ($query) use ($startDate, $endDate, $contract_id): void {
            $query->whereBetween('created_at', [$startDate, $endDate])
                ->where('contract_id_fk', $contract_id);
        })
            ->with([
                'invoices' => function ($query) use ($startDate, $endDate, $lab_id): void {
                    $query->whereBetween('created_at', [$startDate, $endDate])
                        ->where('is_done', 1)
                        ->where('lab_id_fk', $lab_id)
                        ->orderBy('created_at', 'desc')
                        ->with([
                            'patient.user',
                            'paidDetails.paymentMethod',
                            'invoiceTestRels.test',
                            'invoiceTestRels.culture',
                            'invoiceTestRels.package',
                        ]);
                },
            ])
            ->get();
        $lab = User::find($lab_id);

        return response()->json([
            'total_contracts' => $contracts->count(),
            'total_invoices' => $contracts->sum(function ($contract) {
                return $contract->invoices->count();
            }),
            'contracts' => $contracts,
            'lab' => $lab->name,
        ]);
    }

    private function getFromLabReport($startDate, $endDate, $id = null, $lab_id = null)
    {
        $lab_id = $lab_id ?? Auth::user()->id;
        $invoices = Invoice::where('lab_id_fk', $lab_id)
            ->when($id, function ($query) use ($id) {
                $query->where('from_lab_id_fk', $id);
            })
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->where('is_done', 1)
            ->with([
                'patient.user',
                'paidDetails.paymentMethod',
                'contract',
                'invoiceTestRels.test',
                'invoiceTestRels.culture',
                'invoiceTestRels.package',
                'lab',
            ])
            ->orderBy('created_at', 'desc')
            ->get();
        $lab = User::find($lab_id);

        return response()->json([
            'total_invoices' => $invoices->count(),
            'total_amount' => $invoices->sum('total'),
            'invoices' => $invoices,
            'lab' => $lab->name,
        ]);
    }

    private function getToLabReport($startDate, $endDate, $id = null, $lab_id = null)
    {
        $lab_id = $lab_id ?? Auth::user()->id;
        $invoiceIds = InvoiceTestRel::where('to_lab_id_fk', $id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('is_done', 1)
            ->pluck('invoice_id_fk')
            ->unique();

        $invoices = Invoice::whereIn('id', $invoiceIds)
            ->where('lab_id_fk', $lab_id)
            ->orderBy('created_at', 'desc')
            ->with([
                'patient.user',
                'paidDetails.paymentMethod',
                'contract',
                'invoiceTestRels' => function ($query) use ($id, $lab_id): void {
                    $query->where('to_lab_id_fk', $id)
                        ->where('lab_id_fk', $lab_id)
                        ->with(['test', 'culture', 'package', 'testGroup']);
                },
                'lab',
            ])
            ->get();

        // Calculate totals based on the tests/cultures sent to this lab
        $totalAmount = $invoices->sum(function ($invoice) {
            return $invoice->invoiceTestRels->sum('price');
        });
        $lab = User::find($lab_id);

        return response()->json([
            'total_invoices' => $invoices->count(),
            'lab' => $lab->name,
            'total_amount' => $totalAmount,
            'invoices' => $invoices->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'patient_name' => $invoice->patient?->name,
                    'created_at' => $invoice->created_at,
                    'tests' => $invoice->invoiceTestRels->map(function ($rel) {
                        return [
                            'id' => $rel->id,
                            'name' => $rel->test?->name ?? $rel->culture?->name ?? $rel->package?->name,
                            'price' => $rel->price,
                            'is_done' => $rel->is_done,
                            'result' => $rel->result,
                            'created_at' => $rel->created_at,
                        ];
                    }),
                    'total' => $invoice->invoiceTestRels->sum('price'),
                ];
            }),
        ]);
    }

    private function getSampleCollectorsReport($startDate, $endDate, $sample_collector_id = null, $lab_id = null)
    {
        $lab_id = $lab_id ?? Auth::user()->id;
        if (!$sample_collector_id) {
            return response()->json(['message' => 'Sample Collector ID is required'], 400);
        }
        $invoices = Invoice::where('sample_collector_id_fk', $sample_collector_id)
            ->where('lab_id_fk', $lab_id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('is_done', 1)
            ->whereNotNull('sample_collector_id_fk')
            ->orderBy('created_at', 'desc')
            ->with([
                'sampleCollector',
                'patient.user',
                'paidDetails.paymentMethod',
                'invoiceTestRels.test',
                'invoiceTestRels.culture',
                'invoiceTestRels.package',
                'invoiceTestRels.testGroup',
                'lab',
            ])
            ->get();

        $collectorStats = $invoices
            ->groupBy('sample_collector_id_fk')
            ->map(function ($collectorInvoices) {
                $collector = $collectorInvoices->first()->sampleCollector;

                $testCount = $collectorInvoices->sum(function ($invoice) {
                    return $invoice->invoiceTestRels->whereNotNull('test_id_fk')->count();
                });

                $cultureCount = $collectorInvoices->sum(function ($invoice) {
                    return $invoice->invoiceTestRels->whereNotNull('culture_id_fk')->count();
                });

                $packageCount = $collectorInvoices->sum(function ($invoice) {
                    return $invoice->invoiceTestRels->whereNotNull('package_id_fk')->count();
                });

                $totalAmount = $collectorInvoices->sum('total');
                $paidAmount = $collectorInvoices->sum('paid');

                return [
                    'collector_id' => $collector->id,
                    'collector_name' => $collector->name,
                    'total_invoices' => $collectorInvoices->count(),
                    'total_patients' => $collectorInvoices->unique('patient_id_fk')->count(),
                    'total_tests' => $testCount,

                    'total_cultures' => $cultureCount,
                    'total_packages' => $packageCount,
                    'total_samples' => $testCount + $cultureCount + $packageCount,
                    'total_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'due_amount' => $totalAmount - $paidAmount,
                    'invoices' => $collectorInvoices->map(function ($invoice) {
                        return [
                            'invoice_id' => $invoice->id,
                            'patient_name' => $invoice->patient?->name,
                            'created_at' => $invoice->created_at,
                            'total' => $invoice->total,
                            'paid' => $invoice->paid,
                            'due' => $invoice->total - $invoice->paid,
                            'tests_count' => $invoice->invoiceTestRels->whereNotNull('test_id_fk')->count(),
                            'cultures_count' => $invoice->invoiceTestRels->whereNotNull('culture_id_fk')->count(),
                            'packages_count' => $invoice->invoiceTestRels->whereNotNull('package_id_fk')->count(),
                        ];
                    }),
                ];
            })->values();

        $lab = User::find($lab_id);

        return response()->json([
            'total_collectors' => $collectorStats->count(),
            'total_invoices' => $invoices->count(),
            'lab' => $lab->name,
            'total_amount' => $invoices->sum('total'),
            'paid_amount' => $invoices->sum('paid'),
            'due_amount' => $invoices->sum('total') - $invoices->sum('paid'),
            'total_patients' => $invoices->unique('patient_id_fk')->count(),
            'collectors' => $collectorStats,
            'summary' => [
                'total_tests' => $invoices->sum(function ($invoice) {
                    return $invoice->invoiceTestRels->whereNotNull('test_id_fk')->count();
                }),
                'total_cultures' => $invoices->sum(function ($invoice) {
                    return $invoice->invoiceTestRels->whereNotNull('culture_id_fk')->count();
                }),
                'total_packages' => $invoices->sum(function ($invoice) {
                    return $invoice->invoiceTestRels->whereNotNull('package_id_fk')->count();
                }),
            ],
        ]);
    }

    private function getReferralsReport($startDate, $endDate, $referral_id = null, $lab_id = null)
    {
        $lab_id = $lab_id ?? Auth::user()->id;
        if (!$referral_id) {
            return response()->json(['message' => 'Referral ID is required'], 400);
        }
        $alltests = [];
        $allcultures = [];
        $allpackages = [];
        $alltestGroups = [];

        $allreferals = [];

        $paidAmount = 0;

        $invoices = Invoice::where('referral_id_fk', $referral_id)
            ->where('lab_id_fk', $lab_id)
            ->whereBetween('created_at', [$startDate, $endDate])->where('is_done', 1)
            ->orderBy('created_at', 'desc')
            ->with([
                'patient.user',
                'paidDetails.paymentMethod',
                'contract',
                'lab',
                'referral.user.role',
                'invoiceTestRels.test',
                'invoiceTestRels.culture',
                'invoiceTestRels.package',
                'invoiceTestRels.testGroup',
            ])
            ->get();
        $invoicesData = $invoices->map(function ($invoice) use (&$alltests, &$allcultures, &$allpackages, &$allreferals, &$paidAmount) {
            $referal = null;
            if ($invoice->referral != null) {
                $referal = [
                    'name' => $invoice->referral?->name,
                    'commission' => $invoice->referral?->commission,
                ];

            } else {
                return [];
            }
            $testsFromInvoices = [];
            $culturesFromInvoices = [];
            $packagesFromInvoices = [];
            $testGroupsFromInvoices = [];
            foreach ($invoice->invoiceTestRels as $invoiceTestRel) {
                if ($invoiceTestRel->test != null) {

                    $testsFromInvoices[] = [
                        'name' => $invoiceTestRel->test->name,
                        'price' => $invoiceTestRel->price,
                        'invoice_id' => $invoiceTestRel->invoice_id_fk,
                        'created_at' => $invoiceTestRel->created_at,
                    ];
                }
                if ($invoiceTestRel->culture != null) {

                    $culturesFromInvoices[] = [
                        'name' => $invoiceTestRel->culture->name,
                        'price' => $invoiceTestRel->price,
                        'invoice_id' => $invoiceTestRel->invoice_id_fk,
                        'created_at' => $invoiceTestRel->created_at,
                    ];

                }
                if ($invoiceTestRel->package != null) {
                    $packagesFromInvoices[] = [
                        'name' => $invoiceTestRel->package->name,
                        'price' => $invoiceTestRel->price,
                        'invoice_id' => $invoiceTestRel->invoice_id_fk,
                        'created_at' => $invoiceTestRel->created_at,
                    ];

                }
                if ($invoiceTestRel->testGroup != null) {
                    $testGroupsFromInvoices[] = [
                        'name' => $invoiceTestRel->testGroup,
                        'price' => $invoiceTestRel->price,
                        'invoice_id' => $invoiceTestRel->invoice_id_fk,
                        'created_at' => $invoiceTestRel->created_at,
                    ];

                }
            }

            foreach ($testsFromInvoices as $test) {
                $alltests[] = $test;
            }

            foreach ($culturesFromInvoices as $culture) {
                $allcultures[] = $culture;
            }

            foreach ($packagesFromInvoices as $package) {
                $allpackages[] = $package;
            }

            foreach ($testGroupsFromInvoices as $testGroup) {
                $alltestGroups[] = $testGroup;
            }

            $allreferals[] = $referal;
            $paidAmount += $invoice->paidDetails?->sum('amount') ?? 0;

            return [
                'tests' => array_merge($testsFromInvoices, $culturesFromInvoices, $packagesFromInvoices, $testGroupsFromInvoices),
                'referal' => $referal['name'],
                'invoice_id' => $invoice->id,
                'patient_name' => $invoice->patient?->name,
                'created_by' => $invoice->lab?->name,
                'subtotal' => $invoice->sub_total,
                'discount' => $invoice->discount,
                'total' => $invoice->total,
                'due' => $invoice->total - $invoice->paid,
                'created_at' => $invoice->created_at,
            ];

        });
        $totalAmount = $invoices->sum('total');

        $discount = $invoices->sum('discount');
        $subtotal = $invoices->sum('sub_total');
        $due = ($totalAmount) - $paidAmount;
        $referalCommission = collect($allreferals)->sum('commission');
        $profit = $subtotal - $referalCommission;

        $lab = User::find($lab_id);

        return response()->json([
            'total_invoices' => $invoices->count(),
            'total_amount' => $totalAmount,
            'lab' => $lab->name,
            'referal_commission' => $referalCommission,
            'invoices' => $invoicesData->filter(),
            'tests' => collect($alltests)->flatten(1)
                ->filter()
                ->groupBy('name')
                ->map(function ($group) {
                    return [
                        'name' => $group->first()['name'] ?? 'Unknown',
                        'count' => $group->count(),
                        'total' => $group->sum('price'),
                    ];
                })->values(),

            'cultures' => collect($allcultures)->flatten(1)
                ->filter()
                ->groupBy('name')
                ->map(function ($group) {
                    return [
                        'name' => $group->first()['name'] ?? 'Unknown',
                        'count' => $group->count(),
                        'total' => $group->sum('price'),
                    ];
                })->values(),
            'test_groups' => collect($alltestGroups)->flatten(1)
                ->filter()
                ->groupBy('name')
                ->map(function ($group) {
                    return [
                        'name' => $group->first()['name'] ?? 'Unknown',
                        'count' => $group->count(),
                        'total' => $group->sum('price'),
                    ];
                })->values(),
            'packages' => collect($allpackages)->flatten(1)
                ->filter()
                ->groupBy('name')
                ->map(function ($group) {
                    return [
                        'name' => $group->first()['name'] ?? 'Unknown',
                        'count' => $group->count(),
                        'total' => $group->sum('price'),
                    ];
                })->values(),

            'referals' => collect($allreferals)->flatten(1)
                ->filter()
                ->groupBy('name')
                ->map(function ($group) use ($invoicesData) {
                    $firstReferal = $group->first();
                    $referalName = $firstReferal['name'] ?? 'Unknown';

                    // Filter invoices by referal name
                    $referalInvoicesTotal = $invoicesData
                        ->filter(fn($inv) => ($inv['referal'] ?? null) === $referalName)
                        ->sum('total');

                    return [
                        'name' => $referalName,
                        'count' => $group->count(),
                        'total_invoices' => $referalInvoicesTotal,
                        'total' => $group->sum('commission'),
                        'profit' => $referalInvoicesTotal - $group->sum('commission'),
                    ];
                })->values(),

        ]);
    }

    /**
     * Get Quick Summary Report with 4 sections:
     * 1. Lab to Lab (sublabs to main lab)
     * 2. Doctor Referrals
     * 3. Most Requested Tests (top 5)
     * 4. Daily Revenue
     */
    private function getQuickSummaryReport($startDate, $endDate, $lab_id = null)
    {
        $lab_id = $lab_id ?? Auth::user()->id;

        $invoices = Invoice::where('lab_id_fk', $lab_id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with([
                'patient',
                'fromLab',
                'referral',
                'paidDetails',
                'invoiceTestRels.test',
                'invoiceTestRels.culture',
                'invoiceTestRels.package',
                'invoiceTestRels.testGroup',
            ])
            ->get();

        $lab = User::find($lab_id);

        // ========================================
        // 1. LAB TO LAB REPORT (Sublabs to Main Lab)
        // ========================================
        $labToLabReport = [];
        $labsData = [];

        foreach ($invoices as $invoice) {
            if ($invoice->from_lab_id_fk) {
                $fromLabId = $invoice->from_lab_id_fk;

                // Initialize lab data if not exists
                if (!isset($labsData[$fromLabId])) {
                    $labsData[$fromLabId] = [
                        'lab_name' => $invoice->fromLab ? $invoice->fromLab->name : 'Unknown Lab',
                        'patients' => [],
                        'tests_count' => 0,
                        'cultures_count' => 0,
                        'packages_count' => 0,
                        'test_groups_count' => 0,
                        'total_amount' => 0,
                        'paid_amount' => 0,
                    ];
                }

                // Track unique patients per lab
                if (!in_array($invoice->patient_id_fk, $labsData[$fromLabId]['patients'])) {
                    $labsData[$fromLabId]['patients'][] = $invoice->patient_id_fk;
                }

                // Count tests, cultures, packages, and test groups
                foreach ($invoice->invoiceTestRels as $testRel) {
                    if ($testRel->test_id_fk) {
                        $labsData[$fromLabId]['tests_count']++;
                    }
                    if ($testRel->culture_id_fk) {
                        $labsData[$fromLabId]['cultures_count']++;
                    }
                    if ($testRel->package_id_fk) {
                        $labsData[$fromLabId]['packages_count']++;
                    }
                    if ($testRel->test_group_id_fk) {
                        $labsData[$fromLabId]['test_groups_count']++;
                    }
                }

                // Calculate amounts
                $labsData[$fromLabId]['total_amount'] += $invoice->total ?? 0;
                $labsData[$fromLabId]['paid_amount'] += $invoice->paid ?? 0;
            }
        }

        // Format lab to lab report
        foreach ($labsData as $labId => $data) {
            $labToLabReport[] = [
                'lab_name' => $data['lab_name'],
                'referred_patients_count' => count($data['patients']),
                'tests_count' => $data['tests_count'],
                'cultures_count' => $data['cultures_count'],
                'packages_count' => $data['packages_count'],
                'test_groups_count' => $data['test_groups_count'],
                'total_items' => $data['tests_count'] + $data['cultures_count'] + $data['packages_count'] + $data['test_groups_count'],
                'total_amount' => round($data['total_amount'], 2),
                'paid_amount' => round($data['paid_amount'], 2),
                'remaining_amount' => round($data['total_amount'] - $data['paid_amount'], 2),
            ];
        }

        // ========================================
        // 2. DOCTOR REFERRALS REPORT
        // ========================================
        $doctorReferrals = [];
        $doctorsData = [];

        foreach ($invoices as $invoice) {
            if ($invoice->referral_id_fk) {
                $referralId = $invoice->referral_id_fk;

                // Initialize doctor data if not exists
                if (!isset($doctorsData[$referralId])) {
                    $doctorsData[$referralId] = [
                        'doctor_name' => $invoice->referral ? $invoice->referral->name : 'Unknown Doctor',
                        'patients' => [],
                    ];
                }

                // Track unique patients per doctor
                if (!in_array($invoice->patient_id_fk, $doctorsData[$referralId]['patients'])) {
                    $doctorsData[$referralId]['patients'][] = $invoice->patient_id_fk;
                }
            }
        }

        // Format doctor referrals report
        foreach ($doctorsData as $doctorId => $data) {
            $doctorReferrals[] = [
                'doctor_name' => $data['doctor_name'],
                'referred_patients_count' => count($data['patients']),
            ];
        }

        // Sort by referred patients count (descending)
        usort($doctorReferrals, function ($a, $b) {
            return $b['referred_patients_count'] - $a['referred_patients_count'];
        });

        // ========================================
        // 3. MOST REQUESTED TESTS (Top 5)
        // ========================================
        $testsCount = [];

        foreach ($invoices as $invoice) {
            foreach ($invoice->invoiceTestRels as $testRel) {
                // Count individual tests
                if ($testRel->test_id_fk && $testRel->test) {
                    $testName = $testRel->test->name;
                    if (!isset($testsCount[$testName])) {
                        $testsCount[$testName] = 0;
                    }
                    $testsCount[$testName]++;
                }

                // Count tests within packages
                if ($testRel->package_id_fk && $testRel->package_tests) {
                    $pkgTests = is_string($testRel->package_tests) ? json_decode($testRel->package_tests, true) : $testRel->package_tests;
                    foreach ($pkgTests ?? [] as $packageTest) {
                        if (isset($packageTest['name'])) {
                            $testName = $packageTest['name'];
                            if (!isset($testsCount[$testName])) {
                                $testsCount[$testName] = 0;
                            }
                            $testsCount[$testName]++;
                        }
                    }
                }

                // Count tests within test groups
                if ($testRel->test_group_id_fk && $testRel->test_group_tests) {
                    $grpTests = is_string($testRel->test_group_tests) ? json_decode($testRel->test_group_tests, true) : $testRel->test_group_tests;
                    foreach ($grpTests ?? [] as $groupTest) {
                        if (isset($groupTest['name'])) {
                            $testName = $groupTest['name'];
                            if (!isset($testsCount[$testName])) {
                                $testsCount[$testName] = 0;
                            }
                            $testsCount[$testName]++;
                        }
                    }
                }
            }
        }

        // Sort tests by count and get top 5
        arsort($testsCount);
        $topTests = array_slice($testsCount, 0, 5, true);

        $mostRequestedTests = [];
        foreach ($topTests as $testName => $count) {
            $mostRequestedTests[] = [
                'test_name' => $testName,
                'total_done' => $count,
            ];
        }

        // ========================================
        // 4. DAILY REVENUE REPORT
        // ========================================
        $dailyRevenue = [];

        foreach ($invoices as $invoice) {
            $date = $invoice->created_at->format('Y-m-d');

            if (!isset($dailyRevenue[$date])) {
                $dailyRevenue[$date] = [
                    'date' => $date,
                    'total_invoices' => 0,
                    'total_amount' => 0,
                    'paid_amount' => 0,
                    'remaining_amount' => 0,
                ];
            }

            $dailyRevenue[$date]['total_invoices']++;
            $dailyRevenue[$date]['total_amount'] += $invoice->total ?? 0;
            $dailyRevenue[$date]['paid_amount'] += $invoice->paid ?? 0;
            $dailyRevenue[$date]['remaining_amount'] += ($invoice->total ?? 0) - ($invoice->paid ?? 0);
        }

        // Sort by date (newest first)
        krsort($dailyRevenue);

        // Format daily revenue (round amounts)
        $dailyRevenue = array_map(function ($day) {
            return [
                'date' => $day['date'],
                'total_invoices' => $day['total_invoices'],
                'total_amount' => round($day['total_amount'], 2),
                'paid_amount' => round($day['paid_amount'], 2),
                'remaining_amount' => round($day['remaining_amount'], 2),
            ];
        }, array_values($dailyRevenue));

        // ========================================
        // RETURN COMPLETE REPORT
        // ========================================
        return response()->json([
            'date_range' => [
                'from' => $startDate->format('Y-m-d'),
                'to' => $endDate->format('Y-m-d'),
            ],
            'lab' => $lab->name,
            'summary' => [
                'total_invoices' => $invoices->count(),
                'total_patients' => $invoices->unique('patient_id_fk')->count(),
                'total_revenue' => round($invoices->sum('total'), 2),
                // Sum from paidDetails relation — single source of truth (invoices.paid column may drift on old rows)
                'total_paid' => round($invoices->sum(fn ($i) => $i->paidDetails->sum('amount')), 2),
                'total_remaining' => round($invoices->sum('total') - $invoices->sum(fn ($i) => $i->paidDetails->sum('amount')), 2),
            ],
            'lab_to_lab' => $labToLabReport,
            'doctor_referrals' => $doctorReferrals,
            'most_requested_tests' => $mostRequestedTests,
            'daily_revenue' => $dailyRevenue,
        ]);
    }

    /**
     * Dashboard stats endpoint — replaces client-side computation
     * Returns: tests/cultures stats, today's stats, recent invoices
     */
    public function getDashboardStats()
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('reports view')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $labId = $user->role_id == 1 ? null : $user->id;
        $today = Carbon::today();

        // Base query for all invoices (for tests/cultures stats)
        $baseQuery = Invoice::query();
        if ($labId) {
            $baseQuery->where('lab_id_fk', $labId);
        }

        // Tests & cultures stats via invoice_test_rels
        $testStats = InvoiceTestRel::query()
            ->when($labId, fn($q) => $q->whereHas('invoice', fn($iq) => $iq->where('lab_id_fk', $labId)))
            ->whereNotNull('test_id_fk')
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN is_done = true THEN 1 ELSE 0 END) as completed')
            ->first();

        $cultureStats = InvoiceTestRel::query()
            ->when($labId, fn($q) => $q->whereHas('invoice', fn($iq) => $iq->where('lab_id_fk', $labId)))
            ->whereNotNull('culture_id_fk')
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN is_done = true THEN 1 ELSE 0 END) as completed')
            ->first();

        // Today's stats
        $todayQuery = Invoice::whereDate('created_at', $today);
        if ($labId) {
            $todayQuery->where('lab_id_fk', $labId);
        }
        $todayInvoices = $todayQuery->get();

        $todayPatientsCount = $todayInvoices->pluck('patient_id_fk')->filter()->unique()->count();
        $todayRevenue = $todayInvoices->sum('total');
        $todayTestsCount = InvoiceTestRel::query()
            ->when($labId, fn($q) => $q->whereHas('invoice', fn($iq) => $iq->where('lab_id_fk', $labId)))
            ->whereHas('invoice', fn($q) => $q->whereDate('created_at', $today))
            ->whereNotNull('test_id_fk')
            ->count();

        // Recent 10 invoices
        $recentQuery = Invoice::with(['patient.user', 'invoiceTestRels'])
            ->orderBy('created_at', 'desc')
            ->limit(10);
        if ($labId) {
            $recentQuery->where('lab_id_fk', $labId);
        }
        $recentInvoices = $recentQuery->get()->map(fn($inv) => [
            'id' => $inv->id,
            'patient' => $inv->patient?->user?->name ?? $inv->patient?->name ?? 'Unknown',
            'total' => $inv->total,
            'paid' => $inv->paid,
            'status' => $inv->is_done ? 'completed' : 'pending',
            'date' => $inv->created_at,
            'tests_count' => $inv->invoiceTestRels->count(),
        ]);

        return response()->json([
            'tests_stats' => [
                'total' => (int) ($testStats->total ?? 0),
                'completed' => (int) ($testStats->completed ?? 0),
                'pending' => (int) (($testStats->total ?? 0) - ($testStats->completed ?? 0)),
            ],
            'cultures_stats' => [
                'total' => (int) ($cultureStats->total ?? 0),
                'completed' => (int) ($cultureStats->completed ?? 0),
                'pending' => (int) (($cultureStats->total ?? 0) - ($cultureStats->completed ?? 0)),
            ],
            'today_stats' => [
                'invoices' => $todayInvoices->count(),
                'patients' => $todayPatientsCount,
                'revenue' => $todayRevenue,
                'tests' => $todayTestsCount,
            ],
            'recent_invoices' => $recentInvoices,
        ]);
    }
}
