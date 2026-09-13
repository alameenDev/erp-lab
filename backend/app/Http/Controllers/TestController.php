<?php

namespace App\Http\Controllers;

use App\Http\Resources\TestResource;
use App\Models\AgeUnit;
use App\Models\Category;
use App\Models\DurationUnit;
use App\Models\Gender;
use App\Models\ResultType;
use App\Models\Sample;
use App\Models\Test;
use App\Models\TestGroup;
use App\Models\TestQuestionRel;
use App\Models\TestReferenceRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TestController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (! $user->hasPermissionTo('tests view')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $query = Test::query();

        // Multi-tenant filtering: Non-admin users only see their own lab's tests
        $authUser = Auth::user();
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            $query->whereIn('lab_id_fk', $users_ids);
        }

        // Unified search across name, shortcut, report_name
        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'ilike', '%'.$term.'%')
                    ->orWhere('shortcut', 'ilike', '%'.$term.'%')
                    ->orWhere('report_name', 'ilike', '%'.$term.'%');
            });
        }

        // Apply search filters if provided
        if ($request->has('name') && $request->name !== null && $request->name !== '') {
            $query->where('name', 'ilike', '%'.$request->name.'%');
        }

        if ($request->has('shortcut') && $request->shortcut !== null && $request->shortcut !== '') {
            $query->where('shortcut', 'ilike', '%'.$request->shortcut.'%');
        }

        if ($request->has('interface_code') && $request->interface_code !== null && $request->interface_code !== '') {
            $query->where('interface_code', 'ilike', '%'.$request->interface_code.'%');
        }

        if ($request->has('report_name') && $request->report_name !== null && $request->report_name !== '') {
            $query->where('report_name', 'ilike', '%'.$request->report_name.'%');
        }

        if ($request->has('category_name') && $request->category_name !== null && $request->category_name !== '') {
            $query->whereHas('category', function ($query) use ($request) {
                $query->where('name', 'ilike', '%'.$request->category_name.'%');
            });
        }

        if ($request->has('test_group_name') && $request->test_group_name !== null && $request->test_group_name !== '') {
            $query->whereHas('testGroup', function ($query) use ($request) {
                $query->where('group_name', 'ilike', '%'.$request->test_group_name.'%');
            });
        }

        if ($request->has('sample_name') && $request->sample_name !== null && $request->sample_name !== '') {
            $query->whereHas('sample', function ($query) use ($request) {
                $query->where('sample_name', 'ilike', '%'.$request->sample_name.'%');
            });
        }

        if ($request->has('price') && $request->price !== null && $request->price !== '') {
            $query->where('price', 'ilike', '%'.$request->price.'%');
        }

        $tests = $query->select('tests.*')
            ->selectSub(function ($q) {
                $q->from('invoice_test_rels')
                    ->selectRaw('count(*)')
                    ->whereColumn('invoice_test_rels.test_id_fk', 'tests.id');
            }, 'usage_count')
            ->with([
                'lab:id,name',
                'testGroup:id,group_name',
                'category:id,name',
                'durationUnit:id,unit',
                'sample:id,sample_name',
                'price_list_rel.priceList:id,name',
                'resultType:id,result_type_name',
                'testReferenceRanges.gender:id,gender_type',
                'testReferenceRanges.ageUnit:id,unit_name',
                'questions.answerType:id,answer',
            ])
            ->orderByDesc('usage_count')
            ->orderBy('created_at', 'desc')
            ->get();

        $testsData = TestResource::collection($tests)->resolve();

        // When include_groups is set, also search test groups and merge them into results
        $groupsData = [];
        if ($request->has('include_groups') && $request->include_groups) {
            $groupQuery = TestGroup::with('category', 'culture', 'durationUnit', 'sample', 'lab', 'tests', 'price_list_rel.priceList');

            if ($authUser && $authUser->role_id != 1) {
                $groupQuery->whereIn('lab_id_fk', $users_ids);
            }

            if ($request->filled('search')) {
                $term = $request->search;
                $groupQuery->where(function ($q) use ($term) {
                    $q->where('group_name', 'ilike', '%'.$term.'%')
                        ->orWhere('shortcut', 'ilike', '%'.$term.'%')
                        ->orWhereHas('tests', function ($q2) use ($term) {
                            $q2->where('name', 'ilike', '%'.$term.'%')
                                ->orWhere('shortcut', 'ilike', '%'.$term.'%')
                                ->orWhere('report_name', 'ilike', '%'.$term.'%');
                        });
                });
            } elseif ($request->has('name') && $request->name !== null && $request->name !== '') {
                $groupQuery->where('group_name', 'ilike', '%'.$request->name.'%');
            }

            $groups = $groupQuery->select('test_groups.*')
                ->selectSub(function ($q) {
                    $q->from('invoice_test_rels')
                        ->selectRaw('count(*)')
                        ->whereColumn('invoice_test_rels.test_group_id_fk', 'test_groups.id');
                }, 'usage_count')
                ->orderByDesc('usage_count')
                ->orderBy('created_at', 'desc')
                ->get();

            $groupsData = $groups->map(function ($group) {
                return [
                    'id' => $group->id,
                    'usage_count' => (int) ($group->usage_count ?? 0),
                    'name' => $group->group_name,
                    'type' => 'group',
                    'group_name' => $group->group_name,
                    'shortcut' => $group->shortcut,
                    'category' => $group->category?->name,
                    'original_price' => $group->original_price,
                    'for_customer_price' => $group->for_customer_price ?? 0,
                    'price' => $group->for_customer_price ?? $group->original_price ?? 0,
                    'prices' => $group->price_list_rel->map(function ($price) {
                        return [
                            'id' => $price->id,
                            'price_list_id' => $price->priceList?->id,
                            'price_list_title' => $price->priceList?->name,
                            'original_price' => $price->original_price,
                            'price_for_customer' => $price->price_for_customer - ($price->price_for_customer * ($price->priceList?->discount ?? 0) / 100),
                        ];
                    }),
                    'tests' => $group->tests,
                    'culture' => $group->culture,
                    'sample_name' => $group->sample?->sample_name,
                    'test_duration' => $group->test_duration,
                    'duration_unit' => $group->durationUnit?->unit,
                ];
            });
        }

        // Merge tests + groups, then sort by usage (best used first).
        // Stable sort (PHP 8+) keeps groups above tests when usage ties.
        $mergedData = collect($groupsData)->merge($testsData)
            ->sortByDesc(fn ($item) => $item['usage_count'] ?? 0)
            ->values();

        if ($mergedData->isEmpty()) {
            return response()->json([], 200);
        }

        return response()->json($mergedData);
    }

    /**
     * Search for tests based on various criteria
     *
     * @return JsonResponse
     */
    public function search(Request $request)
    {
        $query = Test::query();

        // Multi-tenant filtering: Non-admin users only see their own lab's tests
        $authUser = Auth::user();
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            $query->whereIn('lab_id_fk', $users_ids);
        }

        // Apply search filters if provided
        if ($request->has('name')) {
            $query->where('name', 'ilike', '%'.$request->name.'%');
        }

        if ($request->has('shortcut')) {
            $query->where('shortcut', 'ilike', '%'.$request->shortcut.'%');
        }

        if ($request->has('test_group_id')) {
            $query->where('test_group_id_fk', $request->test_group_id);
        }

        if ($request->has('category_id')) {
            $query->where('category_id_fk', $request->category_id);
        }

        if ($request->has('result_type_id')) {
            $query->where('result_type_id_fk', $request->result_type_id);
        }

        if ($request->has('sample_id')) {
            $query->where('sample_id_fk', $request->sample_id);
        }

        if ($request->has('interface_code')) {
            $query->where('interface_code', 'ilike', '%'.$request->interface_code.'%');
        }

        // Include relationships
        $query->with([
            'lab:id,name',
            'testGroup:id,group_name',
            'category:id,name',
            'durationUnit:id,unit',
            'sample:id,sample_name',
            'price_list_rel.priceList:id,name',
            'resultType:id,result_type_name',
            'testReferenceRanges.gender:id,gender_type',
            'testReferenceRanges.ageUnit:id,unit_name',
            'questions.answerType:id,answer',
        ]);

        // Order by creation date
        $query->orderBy('created_at', 'desc');

        // Paginate results
        $tests = $query->paginate($request->per_page ?? 10);

        if ($tests->isEmpty()) {
            return response()->json(['message' => 'No tests found matching your criteria'], 200);
        }

        return response()->json([
            'data' => TestResource::collection($tests),
            'pagination' => [
                'total' => $tests->total(),
                'per_page' => $tests->perPage(),
                'current_page' => $tests->currentPage(),
                'last_page' => $tests->lastPage(),
                'from' => $tests->firstItem(),
                'to' => $tests->lastItem(),
            ],
        ]);
    }

    public function show(Request $request)
    {
        $user = Auth::user();
        if (! $user->hasPermissionTo('tests view')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $query = Test::with([
            'testGroup:id,group_name',
            'category:id,name',
            'lab:id,name',
            'durationUnit:id,unit',
            'sample:id,sample_name',
            'price_list_rel.priceList:id,name',
            'resultType:id,result_type_name',
            'testReferenceRanges.gender:id,gender_type',
            'testReferenceRanges.ageUnit:id,unit_name',
            'questions.answerType:id,answer',
        ]);

        // Multi-tenant filtering: Non-admin users only see their own lab's tests
        $authUser = Auth::user();
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            $query->whereIn('lab_id_fk', $users_ids);
        }

        $test = $query->find($request->id);
        if (! $test) {
            return response()->json(['message' => 'Test not found'], 404);
        }

        return response()->json(TestResource::make($test));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (! $user->hasPermissionTo('tests create')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'name' => 'required|string',
            'interface_code' => 'nullable|string',
            'test_group_id_fk' => 'nullable|integer|exists:test_groups,id',
            'selection_type_options' => 'nullable|array',
            'category_id_fk' => 'required|integer|exists:categories,id',
            'shortcut' => 'nullable|string',
            'report_name' => 'nullable|string',
            'order' => 'nullable|integer',
            'result_type_id_fk' => 'required|integer|exists:result_types,id',
            'price' => 'nullable|integer',
            'for_customer_price' => 'nullable|integer',
            'unit' => 'nullable|string',
            'is_contain_status' => 'nullable|integer',
            'is_print_alone' => 'nullable|integer',
            'question_ids_fk' => 'nullable|array',
            'question_ids_fk.*id' => 'nullable|integer|exists:patient_questions,id',
            'sample_id_fk' => 'nullable|integer',
            'test_duration' => 'nullable|integer',
            'duration_unit_id_fk' => 'nullable|integer',
            // reference range //
            'test_reference_ranges' => 'nullable|array',
            'test_reference_ranges.*gender_id_fk' => 'nullable|integer|exists:genders,id',
            'test_reference_ranges.*age' => 'nullable|integer',
            'test_reference_ranges.*age_unit_id_fk' => 'nullable|integer|exists:age_units,id',

            'result_comments' => 'nullable|array',
            'is_special_test' => 'nullable|boolean',
            'content' => 'nullable|array',
            'sub_tests' => 'nullable|array',

        ]);
        try {
            DB::beginTransaction();

            $test = Test::create([
                'name' => $request->name,
                'sample_id_fk' => $request->sample_id_fk,
                'test_duration' => $request->test_duration,
                'duration_unit_id_fk' => $request->duration_unit_id_fk,
                'interface_code' => $request->interface_code,
                'category_id_fk' => $request->category_id_fk,
                'test_group_id_fk' => $request->test_group_id_fk,
                'selection_type_options' => $request->input('selection_type_options'),
                'shortcut' => $request->shortcut,
                'report_name' => $request->report_name,
                'result_comments' => $request->input('result_comments'),
                'price' => $request->price,
                'for_customer_price' => $request->for_customer_price,
                'order' => $request->order,
                'result_type_id_fk' => $request->result_type_id_fk,
                'unit' => $request->unit,
                'is_contain_status' => $request->is_contain_status,
                'is_print_alone' => $request->is_print_alone,
                'is_special_test' => $request->is_special_test,
                'content' => $request->input('content'),
                'sub_tests' => $request->input('sub_tests'),
                'lab_id_fk' => Auth::user()->id,
            ]);

            if ($request->has('test_reference_ranges')) {
                foreach ($request->input('test_reference_ranges') as $range) {

                    $test_reference_range = TestReferenceRange::create([
                        'test_id' => $test->id,
                        'gender_id_fk' => $range['gender_id_fk'] ?? null,
                        'age_from' => $range['age_from'] ?? null,
                        'age_to' => $range['age_to'] ?? null,
                        'age_unit_id_fk' => $range['age_unit_id_fk'] ?? null,
                        'from' => $range['from'] ?? null,
                        'to' => $range['to'] ?? null,
                        'selection_type_options' => $range['test_reference_options'] ?? null,
                        'notes' => $range['notes'] ?? null,
                        'lab_id_fk' => Auth::user()->id,
                    ]);
                    if (! $test_reference_range) {
                        DB::rollBack();

                        return response()->json(['message' => 'Test reference range not created'], 500);
                    }
                    ActivityLogController::storeActivity('إنشاء نطاق مرجعي للاختبار', $test_reference_range);
                }
            }
            if ($request->question_ids_fk) {
                foreach ($request->question_ids_fk as $question_id) {
                    $test_question_rel = TestQuestionRel::create([
                        'lab_id_fk' => Auth::user()->id,
                        'test_id_fk' => $test->id,
                        'question_id_fk' => $question_id,
                    ]);
                    if (! $test_question_rel) {
                        DB::rollBack();

                        return response()->json(['message' => 'Test question rel not created'], 500);
                    }
                }
            }

            ActivityLogController::storeActivity('إنشاء اختبار جديد', $test);
            DB::commit();

            return response()->json(['message' => 'Test created successfully', 'test' => $test], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Test could not be created', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        if (! $user->hasPermissionTo('tests edit')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'name' => 'required|string',
            'interface_code' => 'nullable|string',
            'shortcut' => 'nullable|string',
            'report_name' => 'nullable|string',
            'order' => 'nullable|integer',
            'result_type_id_fk' => 'required|integer',
            'unit' => 'nullable|string',
            'category_id_fk' => 'required|integer|exists:categories,id',
            'is_contain_status' => 'nullable|integer',
            'is_print_alone' => 'nullable|integer',
            'test_group_id_fk' => 'nullable|integer|exists:test_groups,id',
            'question_ids_fk' => 'nullable|array',
            'question_ids_fk.*id' => 'nullable|integer|exists:patient_questions,id',
            'selection_type_options' => 'nullable|array',
            'sample_id_fk' => 'nullable|integer',
            'price' => 'nullable|integer',
            'for_customer_price' => 'nullable|integer',
            'test_duration' => 'nullable|integer',
            'duration_unit_id_fk' => 'nullable|integer',
            // reference range //
            'test_reference_ranges' => 'nullable|array',
            'test_reference_ranges.*gender_id_fk' => 'nullable|integer|exists:genders,id',
            'test_reference_ranges.*age' => 'nullable|integer',
            'test_reference_ranges.*age_unit_id_fk' => 'nullable|integer|exists:age_units,id',

            'result_comments' => 'nullable|array',
            'is_special_test' => 'nullable|boolean',
            'content' => 'nullable|array',
            'sub_tests' => 'nullable|array',

        ]);
        try {
            DB::beginTransaction();

            $query = Test::query();

            // Multi-tenant filtering: Non-admin users can only update their own lab's tests
            $authUser = Auth::user();
            if ($authUser && $authUser->role_id != 1) {
                $users_ids = $this->getTenantUserIds();
                $query->whereIn('lab_id_fk', $users_ids);
            }

            $test = $query->find($request->id);
            if (! $test) {
                return response()->json(['message' => 'Test not found'], 404);
            }
            $test_old = $test->replicate();
            $test->name = $request->name ?? $test->name;
            $test->test_duration = $request->test_duration ?? $test->test_duration;
            $test->duration_unit_id_fk = $request->duration_unit_id_fk ?? $test->duration_unit_id_fk;
            $test->sample_id_fk = $request->sample_id_fk ?? $test->sample_id_fk;
            $test->interface_code = $request->interface_code ?? $test->interface_code;
            $test->test_group_id_fk = $request->test_group_id_fk ?? $test->test_group_id_fk;
            $test->selection_type_options = $request->input('selection_type_options') ?? $test->selection_type_options;
            $test->shortcut = $request->shortcut ?? $test->shortcut;
            $test->category_id_fk = $request->category_id_fk ?? $test->category_id_fk;
            $test->price = $request->price ?? $test->price;
            $test->for_customer_price = $request->for_customer_price ?? $test->for_customer_price;
            $test->report_name = $request->report_name ?? $test->report_name;
            $test->order = $request->order ?? $test->order;
            $test->result_type_id_fk = $request->result_type_id_fk ?? $test->result_type_id_fk;
            $test->unit = $request->unit ?? $test->unit;
            $test->is_contain_status = $request->is_contain_status ?? $test->is_contain_status;
            $test->is_print_alone = $request->is_print_alone ?? $test->is_print_alone;
            $test->result_comments = $request->input('result_comments') ?? $test->result_comments;
            $test->is_special_test = $request->is_special_test ?? $test->is_special_test;
            $test->content = $request->input('content') ?? $test->content;
            $test->sub_tests = $request->input('sub_tests') ?? $test->sub_tests;
            $test->save();

            if (! $test) {
                return response()->json(['message' => 'Test could not be updated'], 404);
            }
            $test->testReferenceRanges()->forceDelete();
            if ($request->has('test_reference_ranges')) {
                foreach ($request->input('test_reference_ranges') as $range) {

                    $test_reference_range = TestReferenceRange::create([
                        'test_id' => $test->id,
                        'gender_id_fk' => $range['gender_id_fk'] ?? null,
                        'age_from' => $range['age_from'] ?? null,
                        'age_to' => $range['age_to'] ?? null,
                        'age_unit_id_fk' => $range['age_unit_id_fk'] ?? null,
                        'from' => $range['from'] ?? null,
                        'to' => $range['to'] ?? null,
                        'selection_type_options' => $range['test_reference_options'] ?? null,
                        'notes' => $range['notes'] ?? null,
                        'lab_id_fk' => Auth::user()->id,
                    ]);
                    if (! $test_reference_range) {
                        DB::rollBack();

                        return response()->json(['message' => 'Test reference range not created'], 500);
                    }
                    ActivityLogController::storeActivity('إنشاء نطاق مرجعي للاختبار', $test_reference_range);
                }
            }

            TestQuestionRel::where('test_id_fk', $test->id)->forceDelete();
            if ($request->question_ids_fk) {
                foreach ($request->question_ids_fk as $question_id) {
                    $test_question_rel = TestQuestionRel::create([
                        'lab_id_fk' => Auth::user()->id,
                        'test_id_fk' => $test->id,
                        'question_id_fk' => $question_id,
                    ]);
                    if (! $test_question_rel) {
                        DB::rollBack();

                        return response()->json(['message' => 'Test question rel not created'], 500);
                    }
                }
            }

            ActivityLogController::updateActivity('تعديل اختبار', $test_old, $test);
            DB::commit();

            return response()->json(['message' => 'Test updated successfully', 'test' => $test], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Test could not be updated', 'error' => $e->getMessage()], 500);
        }
    }

    public function getTestQuestions(Request $request)
    {
        $tests = Test::whereIn('id', $request->tests_ids)
            ->with('questions.answerType')
            ->get();

        $questionsData = [];
        $resultComments = [];
        foreach ($tests as $test) {
            foreach ($test->questions as $question) {
                $questionsData[] = [
                    'id' => $question->id,
                    'test_id_fk' => $test->id,
                    'question' => $question->question,
                    'answer_type' => $question->answerType?->answer ?? 'text',
                    'answer_type_id_fk' => $question->answer_type_id_fk,
                    'answer_type_selection_values' => $question->answer_type_selection_values,
                ];
            }
            if (! empty($test->result_comments)) {
                $resultComments[] = [
                    'test_id' => $test->id,
                    'test_name' => $test->name,
                    'comments' => $test->result_comments,
                ];
            }
        }

        return response()->json([
            'questions' => $questionsData,
            'result_comments' => $resultComments,
        ]);
    }

    public function destroy(Request $request)
    {
        $user = Auth::user();
        if (! $user->hasPermissionTo('tests delete')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $query = Test::query();

        // Multi-tenant filtering: Non-admin users can only delete their own lab's tests
        $authUser = Auth::user();
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            $query->whereIn('lab_id_fk', $users_ids);
        }

        $test = $query->find($request->id);
        if (! $test) {
            return response()->json(['message' => 'Test not found'], 404);
        }
        $test->delete();
        ActivityLogController::deleteActivity('حذف اختبار', $test);

        return response()->json(['message' => 'Test deleted successfully']);
    }

    /**
     * Download the Excel import template
     */
    public function downloadTemplate()
    {
        $path = storage_path('app/templates/tests_import_template.xlsx');

        if (! file_exists($path)) {
            return response()->json(['message' => 'Template file not found'], 404);
        }

        return response()->download($path, 'tests_import_template.xlsx');
    }

    /**
     * Get import translations based on locale
     */
    private function importTranslations(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'row' => 'الصف',
                'required' => 'مطلوب',
                'must_not_exceed' => 'يجب ألا يتجاوز',
                'characters' => 'حرف',
                'got' => 'الحالي',
                'must_be_positive_number' => 'يجب أن يكون رقماً موجباً',
                'must_be_positive_integer' => 'يجب أن يكون عدداً صحيحاً موجباً',
                'not_found' => 'غير موجود',
                'available' => 'المتاح',
                'none_create_first' => 'لا يوجد (أنشئها أولاً)',
                'invalid' => 'غير صالح',
                'must_be_one_of' => 'يجب أن يكون أحد',
                'required_when_duration' => 'مطلوب عند تحديد "مدة الاختبار"',
                'duplicate_in_file' => 'مكرر في الملف',
                'same_as_row' => 'نفس الصف',
                'already_exists' => 'موجود مسبقاً في اختباراتك',
                'file_empty' => 'الملف فارغ أو يحتوي فقط على العناوين',
                'file_empty_detail' => 'يجب أن يحتوي ملف Excel على صف بيانات واحد على الأقل بعد صف العناوين.',
                'missing_columns' => 'قالب غير صالح: أعمدة مطلوبة مفقودة',
                'missing_columns_detail' => 'أعمدة مطلوبة مفقودة في العنوان',
                'use_template' => 'يرجى استخدام القالب المتاح.',
                'too_many_rows' => 'عدد الصفوف كبير جداً',
                'file_contains' => 'الملف يحتوي على',
                'data_rows' => 'صف بيانات',
                'max_allowed' => 'الحد الأقصى المسموح به',
                'rows_per_import' => 'صف لكل استيراد',
                'no_tests_imported' => 'لم يتم استيراد أي اختبارات بسبب أخطاء التحقق',
                'success_imported' => 'تم استيراد',
                'tests_word' => 'اختبار بنجاح',
                'rows_skipped' => 'صف تم تخطيه',
                'invalid_file' => 'ملف Excel غير صالح',
                'invalid_file_detail' => 'لا يمكن قراءة الملف. تأكد من أنه ملف .xlsx أو .xls صالح.',
                'import_failed' => 'فشل الاستيراد',
                // Field names
                'field_name' => 'الاسم',
                'field_shortcut' => 'الاختصار',
                'field_report_name' => 'اسم التقرير',
                'field_category' => 'الفئة',
                'field_sample' => 'العينة',
                'field_result_type' => 'نوع النتيجة',
                'field_price' => 'السعر',
                'field_for_customer_price' => 'سعر العميل',
                'field_order' => 'الترتيب',
                'field_test_duration' => 'مدة الاختبار',
                'field_duration_unit' => 'وحدة المدة',
                'field_unit' => 'الوحدة',
                'field_interface_code' => 'رمز الواجهة',
                'field_gender' => 'الجنس',
                'field_age_unit' => 'وحدة العمر',
                'field_age_from' => 'العمر من',
                'field_age_to' => 'العمر إلى',
                'field_range_from' => 'النطاق من',
                'field_range_to' => 'النطاق إلى',
                'field_range_notes' => 'ملاحظات النطاق',
                'age_unit_required_with_gender' => 'مطلوب عند تحديد "الجنس"',
                'age_from_required_with_gender' => 'مطلوب عند تحديد "الجنس"',
                'age_to_required_with_gender' => 'مطلوب عند تحديد "الجنس"',
                'reference_ranges_word' => 'نطاق مرجعي',
            ];
        }

        return [
            'row' => 'Row',
            'required' => 'is required',
            'must_not_exceed' => 'must not exceed',
            'characters' => 'characters',
            'got' => 'got',
            'must_be_positive_number' => 'must be a positive number',
            'must_be_positive_integer' => 'must be a positive integer',
            'not_found' => 'not found',
            'available' => 'Available',
            'none_create_first' => 'none (create them first)',
            'invalid' => 'is invalid',
            'must_be_one_of' => 'Must be one of',
            'required_when_duration' => 'is required when "test_duration" is provided',
            'duplicate_in_file' => 'Duplicate in file',
            'same_as_row' => 'same as row',
            'already_exists' => 'already exists in your tests',
            'file_empty' => 'The file is empty or contains only headers',
            'file_empty_detail' => 'The Excel file must contain at least one data row after the header.',
            'missing_columns' => 'Invalid template: missing required columns',
            'missing_columns_detail' => 'Missing required columns in header',
            'use_template' => 'Please use the provided template.',
            'too_many_rows' => 'Too many rows',
            'file_contains' => 'The file contains',
            'data_rows' => 'data rows',
            'max_allowed' => 'Maximum allowed is',
            'rows_per_import' => 'rows per import',
            'no_tests_imported' => 'No tests were imported due to validation errors',
            'success_imported' => 'Successfully imported',
            'tests_word' => 'tests',
            'rows_skipped' => 'rows skipped',
            'invalid_file' => 'Invalid Excel file',
            'invalid_file_detail' => 'The file could not be read. Please ensure it is a valid .xlsx or .xls file.',
            'import_failed' => 'Import failed',
            // Field names
            'field_name' => 'name',
            'field_shortcut' => 'shortcut',
            'field_report_name' => 'report_name',
            'field_category' => 'category',
            'field_sample' => 'sample',
            'field_result_type' => 'result_type',
            'field_price' => 'price',
            'field_for_customer_price' => 'for_customer_price',
            'field_order' => 'order',
            'field_test_duration' => 'test_duration',
            'field_duration_unit' => 'duration_unit',
            'field_unit' => 'unit',
            'field_interface_code' => 'interface_code',
            'field_gender' => 'gender',
            'field_age_unit' => 'age_unit',
            'field_age_from' => 'age_from',
            'field_age_to' => 'age_to',
            'field_range_from' => 'range_from',
            'field_range_to' => 'range_to',
            'field_range_notes' => 'range_notes',
            'age_unit_required_with_gender' => 'is required when "gender" is provided',
            'age_from_required_with_gender' => 'is required when "gender" is provided',
            'age_to_required_with_gender' => 'is required when "gender" is provided',
            'reference_ranges_word' => 'reference range(s)',
        ];
    }

    /**
     * Import tests from an Excel file
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:5120',
        ]);

        $authUser = Auth::user();
        $labId = $authUser->id;
        $locale = $request->input('locale', 'en');
        $t = $this->importTranslations($locale);

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            if (count($rows) < 2) {
                return response()->json([
                    'message' => $t['file_empty'],
                    'created' => 0,
                    'skipped' => 0,
                    'errors' => [$t['file_empty_detail']],
                ], 422);
            }

            // Remove header row
            $header = array_shift($rows);

            // Validate header columns
            $headerValues = array_map(fn ($v) => strtolower(trim((string) ($v ?? ''))), array_values($header));
            $missingHeaders = [];
            foreach (['name', 'shortcut', 'report_name', 'category', 'sample', 'result_type', 'price'] as $required) {
                if (! in_array($required, $headerValues)) {
                    $missingHeaders[] = $required;
                }
            }
            if (! empty($missingHeaders)) {
                return response()->json([
                    'message' => $t['missing_columns'],
                    'created' => 0,
                    'skipped' => 0,
                    'errors' => [$t['missing_columns_detail'].': '.implode(', ', $missingHeaders).'. '.$t['use_template']],
                ], 422);
            }

            // Map header columns
            $columnMap = [
                'A' => 'name',
                'B' => 'shortcut',
                'C' => 'report_name',
                'D' => 'category',
                'E' => 'sample',
                'F' => 'result_type',
                'G' => 'unit',
                'H' => 'price',
                'I' => 'for_customer_price',
                'J' => 'order',
                'K' => 'test_duration',
                'L' => 'duration_unit',
                'M' => 'interface_code',
                'N' => 'gender',
                'O' => 'age_unit',
                'P' => 'age_from',
                'Q' => 'age_to',
                'R' => 'range_from',
                'S' => 'range_to',
                'T' => 'range_notes',
            ];

            // Field name translations
            $fieldNames = [
                'name' => $t['field_name'],
                'shortcut' => $t['field_shortcut'],
                'report_name' => $t['field_report_name'],
                'category' => $t['field_category'],
                'sample' => $t['field_sample'],
                'result_type' => $t['field_result_type'],
                'price' => $t['field_price'],
                'for_customer_price' => $t['field_for_customer_price'],
                'order' => $t['field_order'],
                'test_duration' => $t['field_test_duration'],
                'duration_unit' => $t['field_duration_unit'],
                'unit' => $t['field_unit'],
                'interface_code' => $t['field_interface_code'],
                'gender' => $t['field_gender'],
                'age_unit' => $t['field_age_unit'],
                'age_from' => $t['field_age_from'],
                'age_to' => $t['field_age_to'],
                'range_from' => $t['field_range_from'],
                'range_to' => $t['field_range_to'],
                'range_notes' => $t['field_range_notes'],
            ];

            // Preload lookups
            $categories = Category::where('lab_id_fk', $labId)
                ->pluck('id', 'name')
                ->toArray();

            $samples = Sample::where('lab_id_fk', $labId)
                ->pluck('id', 'sample_name')
                ->toArray();

            $resultTypes = ResultType::pluck('id', 'result_type_name')->toArray();
            $durationUnits = DurationUnit::pluck('id', 'unit')->toArray();
            $genders = Gender::pluck('id', 'gender_type')->toArray();
            $ageUnits = AgeUnit::pluck('id', 'unit_name')->toArray();

            // Track duplicates within the file — now stores test ID for reference range rows
            $seenNames = [];       // name => ['row' => rowNum, 'test_id' => id]
            $seenShortcuts = [];   // shortcut => rowNum

            // Preload existing test names/shortcuts for this lab
            $existingNames = Test::where('lab_id_fk', $labId)
                ->pluck('name')
                ->map(fn ($n) => strtolower(trim($n)))
                ->toArray();
            $existingShortcuts = Test::where('lab_id_fk', $labId)
                ->whereNotNull('shortcut')
                ->pluck('shortcut')
                ->map(fn ($s) => strtolower(trim($s)))
                ->toArray();

            $created = 0;
            $createdRanges = 0;
            $skipped = 0;
            $errors = [];
            $maxRows = 500;

            // Check row limit
            if (count($rows) > $maxRows) {
                return response()->json([
                    'message' => $t['too_many_rows'],
                    'created' => 0,
                    'skipped' => 0,
                    'errors' => ["{$t['file_contains']} ".count($rows)." {$t['data_rows']}. {$t['max_allowed']} {$maxRows} {$t['rows_per_import']}."],
                ], 422);
            }

            DB::beginTransaction();

            foreach ($rows as $rowIndex => $row) {
                $rowNum = $rowIndex + 2;
                $rowErrors = [];

                // Map row to named fields
                $data = [];
                foreach ($columnMap as $col => $field) {
                    $data[$field] = isset($row[$col]) ? trim((string) $row[$col]) : '';
                }

                // Skip completely empty rows
                $allEmpty = true;
                foreach ($data as $v) {
                    if ($v !== '') {
                        $allEmpty = false;
                        break;
                    }
                }
                if ($allEmpty) {
                    continue;
                }

                // Check if this is a duplicate row for adding reference ranges
                $nameLower = strtolower($data['name']);
                $shortcutLower = strtolower($data['shortcut']);
                $isDuplicateRow = isset($seenNames[$nameLower]) && isset($seenShortcuts[$shortcutLower]);

                if (! $isDuplicateRow) {
                    // ===== REQUIRED FIELDS =====
                    foreach (['name', 'shortcut', 'report_name', 'category', 'sample', 'result_type', 'price'] as $req) {
                        if (empty($data[$req])) {
                            $rowErrors[] = "\"{$fieldNames[$req]}\" {$t['required']}";
                        }
                    }

                    if (! empty($rowErrors)) {
                        $errors[] = "{$t['row']} {$rowNum}: ".implode(' | ', $rowErrors);
                        $skipped++;

                        continue;
                    }

                    // ===== STRING LENGTH VALIDATION =====
                    $lengthChecks = [
                        'name' => 255,
                        'shortcut' => 255,
                        'report_name' => 255,
                        'interface_code' => 255,
                        'unit' => 100,
                    ];
                    foreach ($lengthChecks as $field => $maxLen) {
                        if (! empty($data[$field]) && mb_strlen($data[$field]) > $maxLen) {
                            $rowErrors[] = "\"{$fieldNames[$field]}\" {$t['must_not_exceed']} {$maxLen} {$t['characters']} ({$t['got']} ".mb_strlen($data[$field]).')';
                        }
                    }

                    // ===== NUMERIC VALIDATION =====
                    if (! is_numeric($data['price']) || (int) $data['price'] < 0) {
                        $rowErrors[] = "\"{$fieldNames['price']}\" {$t['must_be_positive_number']} ({$t['got']} \"{$data['price']}\")";
                    }
                    if (! empty($data['for_customer_price']) && (! is_numeric($data['for_customer_price']) || (int) $data['for_customer_price'] < 0)) {
                        $rowErrors[] = "\"{$fieldNames['for_customer_price']}\" {$t['must_be_positive_number']} ({$t['got']} \"{$data['for_customer_price']}\")";
                    }
                    if (! empty($data['order']) && (! is_numeric($data['order']) || (int) $data['order'] < 1)) {
                        $rowErrors[] = "\"{$fieldNames['order']}\" {$t['must_be_positive_integer']} ({$t['got']} \"{$data['order']}\")";
                    }
                    if (! empty($data['test_duration']) && (! is_numeric($data['test_duration']) || (int) $data['test_duration'] < 1)) {
                        $rowErrors[] = "\"{$fieldNames['test_duration']}\" {$t['must_be_positive_integer']} ({$t['got']} \"{$data['test_duration']}\")";
                    }

                    // ===== LOOKUP VALIDATION =====
                    $categoryId = $categories[$data['category']] ?? null;
                    if (! $categoryId) {
                        $available = ! empty($categories) ? implode(', ', array_keys($categories)) : $t['none_create_first'];
                        $rowErrors[] = "\"{$fieldNames['category']}\" \"{$data['category']}\" {$t['not_found']}. {$t['available']}: {$available}";
                    }

                    $sampleId = $samples[$data['sample']] ?? null;
                    if (! $sampleId) {
                        $available = ! empty($samples) ? implode(', ', array_keys($samples)) : $t['none_create_first'];
                        $rowErrors[] = "\"{$fieldNames['sample']}\" \"{$data['sample']}\" {$t['not_found']}. {$t['available']}: {$available}";
                    }

                    $resultTypeId = $resultTypes[$data['result_type']] ?? null;
                    if (! $resultTypeId) {
                        $rowErrors[] = "\"{$fieldNames['result_type']}\" \"{$data['result_type']}\" {$t['invalid']}. {$t['must_be_one_of']}: ".implode(', ', array_keys($resultTypes));
                    }

                    $durationUnitId = null;
                    if (! empty($data['duration_unit'])) {
                        $durationUnitId = $durationUnits[$data['duration_unit']] ?? null;
                        if (! $durationUnitId) {
                            $rowErrors[] = "\"{$fieldNames['duration_unit']}\" \"{$data['duration_unit']}\" {$t['invalid']}. {$t['must_be_one_of']}: ".implode(', ', array_keys($durationUnits));
                        }
                    }

                    // ===== CROSS-FIELD VALIDATION =====
                    if (! empty($data['test_duration']) && empty($data['duration_unit'])) {
                        $rowErrors[] = "\"{$fieldNames['duration_unit']}\" {$t['required_when_duration']}";
                    }

                    // ===== DUPLICATE CHECKS (only for non-duplicate rows) =====
                    if (isset($seenNames[$nameLower])) {
                        $rowErrors[] = "{$t['duplicate_in_file']}: \"{$fieldNames['name']}\" \"{$data['name']}\" — {$t['same_as_row']} {$seenNames[$nameLower]['row']}";
                    }
                    if (isset($seenShortcuts[$shortcutLower])) {
                        $rowErrors[] = "{$t['duplicate_in_file']}: \"{$fieldNames['shortcut']}\" \"{$data['shortcut']}\" — {$t['same_as_row']} {$seenShortcuts[$shortcutLower]}";
                    }

                    // ===== DUPLICATE CHECKS (in database) =====
                    if (in_array($nameLower, $existingNames)) {
                        $rowErrors[] = "\"{$fieldNames['name']}\" \"{$data['name']}\" {$t['already_exists']}";
                    }
                    if (in_array($shortcutLower, $existingShortcuts)) {
                        $rowErrors[] = "\"{$fieldNames['shortcut']}\" \"{$data['shortcut']}\" {$t['already_exists']}";
                    }
                }

                // ===== REFERENCE RANGE VALIDATION (for all rows with gender) =====
                $hasRefRange = ! empty($data['gender']);
                $genderId = null;
                $ageUnitId = null;

                if ($hasRefRange) {
                    $genderId = $genders[$data['gender']] ?? null;
                    if (! $genderId) {
                        $rowErrors[] = "\"{$fieldNames['gender']}\" \"{$data['gender']}\" {$t['invalid']}. {$t['must_be_one_of']}: ".implode(', ', array_keys($genders));
                    }

                    if (empty($data['age_unit'])) {
                        $rowErrors[] = "\"{$fieldNames['age_unit']}\" {$t['age_unit_required_with_gender']}";
                    } else {
                        $ageUnitId = $ageUnits[$data['age_unit']] ?? null;
                        if (! $ageUnitId) {
                            $rowErrors[] = "\"{$fieldNames['age_unit']}\" \"{$data['age_unit']}\" {$t['invalid']}. {$t['must_be_one_of']}: ".implode(', ', array_keys($ageUnits));
                        }
                    }

                    if ($data['age_from'] === '') {
                        $rowErrors[] = "\"{$fieldNames['age_from']}\" {$t['age_from_required_with_gender']}";
                    } elseif (! is_numeric($data['age_from']) || (int) $data['age_from'] < 0) {
                        $rowErrors[] = "\"{$fieldNames['age_from']}\" {$t['must_be_positive_number']} ({$t['got']} \"{$data['age_from']}\")";
                    }

                    if ($data['age_to'] === '') {
                        $rowErrors[] = "\"{$fieldNames['age_to']}\" {$t['age_to_required_with_gender']}";
                    } elseif (! is_numeric($data['age_to']) || (int) $data['age_to'] < 0) {
                        $rowErrors[] = "\"{$fieldNames['age_to']}\" {$t['must_be_positive_number']} ({$t['got']} \"{$data['age_to']}\")";
                    }

                    if (! empty($data['range_from']) && ! is_numeric($data['range_from'])) {
                        $rowErrors[] = "\"{$fieldNames['range_from']}\" {$t['must_be_positive_number']} ({$t['got']} \"{$data['range_from']}\")";
                    }
                    if (! empty($data['range_to']) && ! is_numeric($data['range_to'])) {
                        $rowErrors[] = "\"{$fieldNames['range_to']}\" {$t['must_be_positive_number']} ({$t['got']} \"{$data['range_to']}\")";
                    }
                }

                // ===== COLLECT ERRORS OR CREATE =====
                if (! empty($rowErrors)) {
                    $errors[] = "{$t['row']} {$rowNum}: ".implode(' | ', $rowErrors);
                    $skipped++;

                    continue;
                }

                if ($isDuplicateRow) {
                    // This is a duplicate row — only add reference range to existing test
                    $testId = $seenNames[$nameLower]['test_id'];
                    if ($hasRefRange && $testId) {
                        TestReferenceRange::create([
                            'test_id' => $testId,
                            'gender_id_fk' => $genderId,
                            'age_unit_id_fk' => $ageUnitId,
                            'age_from' => (int) $data['age_from'],
                            'age_to' => (int) $data['age_to'],
                            'from' => $data['range_from'] ?: null,
                            'to' => $data['range_to'] ?: null,
                            'notes' => $data['range_notes'] ?: null,
                            'lab_id_fk' => $labId,
                        ]);
                        $createdRanges++;
                    }

                    continue;
                }

                $test = Test::create([
                    'name' => $data['name'],
                    'shortcut' => $data['shortcut'],
                    'report_name' => $data['report_name'],
                    'category_id_fk' => $categoryId,
                    'sample_id_fk' => $sampleId,
                    'result_type_id_fk' => $resultTypeId,
                    'unit' => $data['unit'] ?: null,
                    'price' => (int) $data['price'],
                    'for_customer_price' => ! empty($data['for_customer_price']) ? (int) $data['for_customer_price'] : null,
                    'order' => ! empty($data['order']) ? (int) $data['order'] : null,
                    'test_duration' => ! empty($data['test_duration']) ? (int) $data['test_duration'] : null,
                    'duration_unit_id_fk' => $durationUnitId,
                    'interface_code' => $data['interface_code'] ?: null,
                    'lab_id_fk' => $labId,
                ]);

                // Create reference range if provided
                if ($hasRefRange) {
                    TestReferenceRange::create([
                        'test_id' => $test->id,
                        'gender_id_fk' => $genderId,
                        'age_unit_id_fk' => $ageUnitId,
                        'age_from' => (int) $data['age_from'],
                        'age_to' => (int) $data['age_to'],
                        'from' => $data['range_from'] ?: null,
                        'to' => $data['range_to'] ?: null,
                        'notes' => $data['range_notes'] ?: null,
                        'lab_id_fk' => $labId,
                    ]);
                    $createdRanges++;
                }

                $seenNames[$nameLower] = ['row' => $rowNum, 'test_id' => $test->id];
                $seenShortcuts[$shortcutLower] = $rowNum;
                $existingNames[] = $nameLower;
                $existingShortcuts[] = $shortcutLower;

                $created++;
            }

            if ($created === 0 && ! empty($errors)) {
                DB::rollBack();

                return response()->json([
                    'message' => $t['no_tests_imported'],
                    'created' => 0,
                    'skipped' => $skipped,
                    'errors' => $errors,
                ], 422);
            }

            DB::commit();

            ActivityLogController::storeActivity("استيراد {$created} اختبار و {$createdRanges} نطاق مرجعي من ملف Excel", null);

            $msg = "{$t['success_imported']} {$created} {$t['tests_word']}";
            if ($createdRanges > 0) {
                $msg .= ", {$createdRanges} {$t['reference_ranges_word']}";
            }
            if ($skipped > 0) {
                $msg .= ", {$skipped} {$t['rows_skipped']}";
            }

            return response()->json([
                'message' => $msg,
                'created' => $created,
                'skipped' => $skipped,
                'errors' => $errors,
            ], 200);

        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            return response()->json([
                'message' => $t['invalid_file'],
                'created' => 0,
                'skipped' => 0,
                'errors' => [$t['invalid_file_detail']],
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => $t['import_failed'],
                'created' => 0,
                'skipped' => 0,
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }
}
