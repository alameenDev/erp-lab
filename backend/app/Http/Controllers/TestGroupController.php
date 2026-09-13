<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Culture;
use App\Models\DurationUnit;
use App\Models\Sample;
use App\Models\Test;
use App\Models\TestGroup;
use App\Models\TestGroupComment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TestGroupController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('test groups view')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $query = TestGroup::with('category', 'culture', 'durationUnit', 'sample', 'lab', 'testGroupComments', 'tests', 'price_list_rel.priceList');

        // Admin (role_id = 1) can see all test groups
        if ($user->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            $query->whereIn('lab_id_fk', $users_ids);
        }

        $test_groups = $query->orderBy('created_at', 'desc')->get();
        if (empty($test_groups) == false) {
            $test_group = $test_groups->map(function ($test_group) {
                return [
                    'id' => $test_group->id,
                    'category_id_fk' => $test_group->category_id_fk,
                    'category_name' => $test_group->category?->name,
                    'group_name' => $test_group->group_name,
                    'lab' => $test_group->lab?->name,
                    'shortcut' => $test_group->shortcut,
                    'original_price' => $test_group->original_price,
                    'for_customer_price' => $test_group->for_customer_price,
                    'prices' => $test_group->price_list_rel->map(function ($price) {
                        return [
                            'id' => $price->id,
                            'price_list_id' => $price->priceList?->id,
                            'price_list_name' => $price->priceList?->name,
                            'original_price' => $price->original_price,
                            'price_for_customer' => $price->price_for_customer - ($price->price_for_customer * (($price->priceList?->discount ?? 0) / 100)),
                        ];
                    }),
                    'test_duration' => $test_group->test_duration,
                    'duration_unit_id_fk' => $test_group->durationUnit?->id,
                    'duration_unit' => $test_group->durationUnit?->unit,
                    'sample_name' => $test_group->sample?->sample_name,
                    'sample_id_fk' => $test_group->sample?->id,
                    'precautions' => $test_group->precautions,
                    'formula' => $test_group->formula,
                    'is_print_alone' => $test_group->is_print_alone,
                    'result_comments' => $test_group->result_comments,
                    'tests' => $test_group->tests,
                    'culture' => $test_group->culture,
                    'test_group_comment' => $test_group->testGroupComments?->comment,
                    'lab_id' => $test_group->lab?->id,
                    'created_at' => $test_group->created_at,
                    'updated_at' => $test_group->updated_at,

                ];
            });

            return response()->json($test_group, 200);

        }

        return response()->json($test_groups, 200);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('test groups create')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'category_id_fk' => 'nullable|integer',
            'for_customer_price' => 'nullable|integer',
            'group_name' => 'nullable|string|max:255',
            'shortcut' => 'nullable|string|max:255',
            'original_price' => 'nullable|integer',
            'sample_id_fk' => 'nullable|integer',
            'test_duration' => 'nullable|integer',
            'test_ids' => 'nullable|array',
            'test_ids.*' => 'nullable|integer|exists:tests,id',
            'culture_ids' => 'nullable|array',
            'culture_ids.*' => 'nullable|integer|exists:cultures,id',
            'duration_unit_id_fk' => 'nullable|integer',
            'precautions' => 'nullable|string|max:255',
            'formula' => 'nullable|array',
            'is_print_alone' => 'nullable|integer',
            'result_comments' => 'nullable|array|max:255',
            'test_group_comment' => 'nullable|string|max:255',

        ]);
        $test_group = TestGroup::create([
            'category_id_fk' => $request->category_id_fk,
            'for_customer_price' => $request->for_customer_price,
            'group_name' => $request->group_name,
            'shortcut' => $request->shortcut,
            'original_price' => $request->original_price,
            'sample_id_fk' => $request->sample_id_fk,
            'test_duration' => $request->test_duration,
            'duration_unit_id_fk' => $request->duration_unit_id_fk,
            'precautions' => $request->precautions,
            'formula' => $request->formula,
            'is_print_alone' => $request->is_print_alone,
            'result_comments' => $request->input('result_comments'),
            'lab_id_fk' => Auth::user()->id,
        ]);

        if (!$test_group) {
            return response()->json(['message' => 'Test Group not created'], 404);
        }

        ActivityLogController::storeActivity('إنشاء مجموعة اختبار', $test_group);

        if ($request->test_group_comment) {

            $test_group_comment = TestGroupComment::create([
                'test_group_id_fk' => $test_group->id,
                'comment' => $request->test_group_comment,
                'lab_id_fk' => Auth::user()->id,
            ]);
            ActivityLogController::storeActivity('إنشاء تعليق على مجموعة اختبار', $test_group_comment);
        }

        if (is_array($request->test_ids) && count($request->test_ids) > 0) {
            $syncData = [];
            foreach ($request->test_ids as $idx => $test_id) {
                $syncData[$test_id] = ['order' => $idx + 1];
            }
            $test_group->tests()->sync($syncData);
        }

        if (is_array($request->culture_ids) && count($request->culture_ids) > 0) {
            foreach ($request->culture_ids as $culture_id) {
                $culture = Culture::find($culture_id);
                if (!$culture) {
                    continue;
                }
                $culture->test_group_id_fk = $test_group->id;
                $culture->save();
            }
        }

        return response()->json(['message' => 'Test Group created successfully']);
    }

    public function show(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('test groups view')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $test_group = TestGroup::with(['category', 'culture', 'durationUnit', 'sample', 'lab', 'testGroupComments', 'tests', 'price_list_rel.priceList'])->findOrFail($request->id);
        // $test_group = TestGroup::where('id', $request->id)->with( 'tests')->first();
        if (empty($test_group) || $test_group === null) {
            return response()->json(['message' => 'Test Group not found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (!in_array($test_group->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        // return response()->json($test_group);
        $test_group = [
            'id' => $test_group->id,
            'category_id_fk' => $test_group->category?->id,
            'category_name' => $test_group->category?->name,
            'group_name' => $test_group->group_name,
            'original_price' => $test_group->original_price,
            'for_customer_price' => $test_group->for_customer_price,
            'prices' => $test_group->price_list_rel->map(function ($price) {
                return [
                    'id' => $price->id,
                    'price_list_id' => $price->priceList?->id,
                    'price_list_name' => $price->priceList?->name,
                    'original_price' => $price->original_price,
                    'price_for_customer' => $price->price_for_customer - ($price->price_for_customer * (($price->priceList?->discount ?? 0) / 100)),
                ];
            }),
            'test_duration' => $test_group->test_duration,
            'duration_unit' => $test_group->durationUnit?->unit,
            'duration_unit_id_fk' => $test_group->durationUnit?->id,
            'shortcut' => $test_group->shortcut,
            'precautions' => $test_group->precautions,
            'formula' => $test_group->formula,
            'is_print_alone' => $test_group->is_print_alone,
            'result_comments' => $test_group->result_comments,
            'tests' => $test_group->tests,
            'culture' => $test_group->culture,
            'test_group_comment' => $test_group->testGroupComments?->comment,
            'lab_name' => $test_group->lab?->name,
            'lab_id' => $test_group->lab?->id,
            'sample_name' => $test_group->sample?->sample_name,
            'sample_id_fk' => $test_group->sample?->id,
            'created_at' => $test_group->created_at,
            'updated_at' => $test_group->updated_at,
        ];

        return response()->json($test_group, 200);
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('test groups edit')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'category_id_fk' => 'nullable|integer',
            'for_customer_price' => 'nullable|integer',
            'group_name' => 'nullable|string|max:255',
            'shortcut' => 'nullable|string|max:255',
            'sample_id_fk' => 'nullable|integer',
            'test_ids' => 'nullable|array',
            'test_ids.*' => 'nullable|integer|exists:tests,id',  // Fixed key
            'culture_ids' => 'nullable|array',
            'culture_ids.*' => 'nullable|integer|exists:cultures,id',
            'original_price' => 'nullable|integer',
            'test_duration' => 'nullable|integer',
            'duration_unit_id_fk' => 'nullable|integer',
            'precautions' => 'nullable|string|max:255',
            'formula' => 'nullable|array',
            'is_print_alone' => 'nullable|integer',
            'result_comments' => 'nullable|array|max:255',
            'test_group_comment' => 'nullable|string|max:255',
        ]);

        $test_group = TestGroup::find($request->id);
        if ($test_group === null) {
            return response()->json(['message' => 'Test Group not found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (!in_array($test_group->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $old_group = $test_group->replicate()->setRawAttributes($test_group->getAttributes());
        $test_group->update([
            'category_id_fk' => $request->category_id_fk,
            'for_customer_price' => $request->for_customer_price,
            'group_name' => $request->group_name,
            'shortcut' => $request->shortcut,
            'sample_id_fk' => $request->sample_id_fk,
            'original_price' => $request->original_price,
            'test_duration' => $request->test_duration,
            'duration_unit_id_fk' => $request->duration_unit_id_fk,
            'precautions' => $request->precautions,
            'formula' => $request->formula,
            'is_print_alone' => $request->is_print_alone,
            'result_comments' => $request->input('result_comments') ?? $old_group->result_comments,
        ]);

        ActivityLogController::updateActivity('تعديل مجموعة اختبار', $old_group, $test_group);
        $test_group->testGroupComments()->delete();
        if ($request->test_group_comment) {

            $test_group_comment = TestGroupComment::create([
                'test_group_id_fk' => $test_group->id,
                'comment' => $request->test_group_comment,
                'lab_id_fk' => Auth::user()->id,
            ]);
            ActivityLogController::storeActivity('إنشاء تعليق على مجموعة اختبار', $test_group_comment);
        }

        if (isset($request->test_ids)) {
            $syncData = [];
            foreach ($request->test_ids as $idx => $testId) {
                $syncData[$testId] = ['order' => $idx + 1];
            }
            $test_group->tests()->sync($syncData);
        }

        if (isset($request->culture_ids)) {
            foreach ($request->culture_ids as $idx => $cultureId) {
                Culture::where('id', $cultureId)->update(['test_group_id_fk' => $test_group->id]);
            }
        }

        return response()->json(['message' => 'Test Group updated successfully']);
    }

    public function destroy(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('test groups delete')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $test_group = TestGroup::find($request->id);
        if (!$test_group) {
            return response()->json(['message' => 'Test Group not found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (!in_array($test_group->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $test_group->delete();
        ActivityLogController::deleteActivity('حذف مجموعة اختبار', $test_group);

        return response()->json(['message' => 'Test Group deleted successfully']);
    }

    /**
     * Download the Excel import template
     */
    public function downloadTemplate()
    {
        $path = storage_path('app/templates/test_groups_import_template.xlsx');

        if (!file_exists($path)) {
            return response()->json(['message' => 'Template file not found'], 404);
        }

        return response()->download($path, 'test_groups_import_template.xlsx');
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
                'must_be_0_or_1' => 'يجب أن يكون 0 أو 1',
                'required_when_duration' => 'مطلوب عند تحديد "مدة الاختبار"',
                'duplicate_in_file' => 'مكرر في الملف',
                'same_as_row' => 'نفس الصف',
                'already_exists' => 'موجود مسبقاً في مجموعات اختباراتك',
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
                'no_items_imported' => 'لم يتم استيراد أي مجموعات اختبار بسبب أخطاء التحقق',
                'success_imported' => 'تم استيراد',
                'items_word' => 'مجموعة اختبار بنجاح',
                'rows_skipped' => 'صف تم تخطيه',
                'invalid_file' => 'ملف Excel غير صالح',
                'invalid_file_detail' => 'لا يمكن قراءة الملف. تأكد من أنه ملف .xlsx أو .xls صالح.',
                'import_failed' => 'فشل الاستيراد',
                // Field names
                'field_group_name' => 'اسم المجموعة',
                'field_shortcut' => 'الاختصار',
                'field_category' => 'الفئة',
                'field_sample' => 'العينة',
                'field_original_price' => 'السعر الأصلي',
                'field_for_customer_price' => 'سعر العميل',
                'field_test_duration' => 'مدة الاختبار',
                'field_duration_unit' => 'وحدة المدة',
                'field_precautions' => 'الاحتياطات',
                'field_is_print_alone' => 'طباعة منفردة',
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
            'must_be_0_or_1' => 'must be 0 or 1',
            'required_when_duration' => 'is required when "test_duration" is provided',
            'duplicate_in_file' => 'Duplicate in file',
            'same_as_row' => 'same as row',
            'already_exists' => 'already exists in your test groups',
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
            'no_items_imported' => 'No test groups were imported due to validation errors',
            'success_imported' => 'Successfully imported',
            'items_word' => 'test groups',
            'rows_skipped' => 'rows skipped',
            'invalid_file' => 'Invalid Excel file',
            'invalid_file_detail' => 'The file could not be read. Please ensure it is a valid .xlsx or .xls file.',
            'import_failed' => 'Import failed',
            // Field names
            'field_group_name' => 'group_name',
            'field_shortcut' => 'shortcut',
            'field_category' => 'category',
            'field_sample' => 'sample',
            'field_original_price' => 'original_price',
            'field_for_customer_price' => 'for_customer_price',
            'field_test_duration' => 'test_duration',
            'field_duration_unit' => 'duration_unit',
            'field_precautions' => 'precautions',
            'field_is_print_alone' => 'is_print_alone',
        ];
    }

    /**
     * Import test groups from an Excel file
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
            $headerValues = array_map(fn($v) => strtolower(trim((string) ($v ?? ''))), array_values($header));
            $requiredHeaders = ['group_name', 'shortcut', 'category', 'sample', 'original_price'];
            $missingHeaders = [];
            foreach ($requiredHeaders as $required) {
                if (!in_array($required, $headerValues)) {
                    $missingHeaders[] = $required;
                }
            }
            if (!empty($missingHeaders)) {
                return response()->json([
                    'message' => $t['missing_columns'],
                    'created' => 0,
                    'skipped' => 0,
                    'errors' => [$t['missing_columns_detail'] . ': ' . implode(', ', $missingHeaders) . '. ' . $t['use_template']],
                ], 422);
            }

            // Map header columns
            $columnMap = [
                'A' => 'group_name',
                'B' => 'shortcut',
                'C' => 'category',
                'D' => 'sample',
                'E' => 'original_price',
                'F' => 'for_customer_price',
                'G' => 'test_duration',
                'H' => 'duration_unit',
                'I' => 'precautions',
                'J' => 'is_print_alone',
            ];

            // Field name translations
            $fieldNames = [
                'group_name' => $t['field_group_name'],
                'shortcut' => $t['field_shortcut'],
                'category' => $t['field_category'],
                'sample' => $t['field_sample'],
                'original_price' => $t['field_original_price'],
                'for_customer_price' => $t['field_for_customer_price'],
                'test_duration' => $t['field_test_duration'],
                'duration_unit' => $t['field_duration_unit'],
                'precautions' => $t['field_precautions'],
                'is_print_alone' => $t['field_is_print_alone'],
            ];

            // Preload lookups
            $categories = Category::where('lab_id_fk', $labId)
                ->pluck('id', 'name')
                ->toArray();

            $samples = Sample::where('lab_id_fk', $labId)
                ->pluck('id', 'sample_name')
                ->toArray();

            $durationUnits = DurationUnit::pluck('id', 'unit')->toArray();

            // Track duplicates within the file
            $seenNames = [];
            $seenShortcuts = [];

            // Preload existing group names/shortcuts for this lab
            $existingNames = TestGroup::where('lab_id_fk', $labId)
                ->pluck('group_name')
                ->map(fn($n) => strtolower(trim($n)))
                ->toArray();
            $existingShortcuts = TestGroup::where('lab_id_fk', $labId)
                ->whereNotNull('shortcut')
                ->pluck('shortcut')
                ->map(fn($s) => strtolower(trim($s)))
                ->toArray();

            $created = 0;
            $skipped = 0;
            $errors = [];
            $maxRows = 500;

            // Check row limit
            if (count($rows) > $maxRows) {
                return response()->json([
                    'message' => $t['too_many_rows'],
                    'created' => 0,
                    'skipped' => 0,
                    'errors' => ["{$t['file_contains']} " . count($rows) . " {$t['data_rows']}. {$t['max_allowed']} {$maxRows} {$t['rows_per_import']}."],
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

                // ===== REQUIRED FIELDS =====
                foreach (['group_name', 'shortcut', 'category', 'sample', 'original_price'] as $req) {
                    if (empty($data[$req])) {
                        $rowErrors[] = "\"{$fieldNames[$req]}\" {$t['required']}";
                    }
                }

                if (!empty($rowErrors)) {
                    $errors[] = "{$t['row']} {$rowNum}: " . implode(' | ', $rowErrors);
                    $skipped++;
                    continue;
                }

                // ===== STRING LENGTH VALIDATION =====
                $lengthChecks = [
                    'group_name' => 255,
                    'shortcut' => 255,
                    'precautions' => 255,
                ];
                foreach ($lengthChecks as $field => $maxLen) {
                    if (!empty($data[$field]) && mb_strlen($data[$field]) > $maxLen) {
                        $rowErrors[] = "\"{$fieldNames[$field]}\" {$t['must_not_exceed']} {$maxLen} {$t['characters']} ({$t['got']} " . mb_strlen($data[$field]) . ")";
                    }
                }

                // ===== NUMERIC VALIDATION =====
                if (!is_numeric($data['original_price']) || (int) $data['original_price'] < 0) {
                    $rowErrors[] = "\"{$fieldNames['original_price']}\" {$t['must_be_positive_number']} ({$t['got']} \"{$data['original_price']}\")";
                }
                if (!empty($data['for_customer_price']) && (!is_numeric($data['for_customer_price']) || (int) $data['for_customer_price'] < 0)) {
                    $rowErrors[] = "\"{$fieldNames['for_customer_price']}\" {$t['must_be_positive_number']} ({$t['got']} \"{$data['for_customer_price']}\")";
                }
                if (!empty($data['test_duration']) && (!is_numeric($data['test_duration']) || (int) $data['test_duration'] < 1)) {
                    $rowErrors[] = "\"{$fieldNames['test_duration']}\" {$t['must_be_positive_integer']} ({$t['got']} \"{$data['test_duration']}\")";
                }
                if ($data['is_print_alone'] !== '' && !in_array($data['is_print_alone'], ['0', '1'])) {
                    $rowErrors[] = "\"{$fieldNames['is_print_alone']}\" {$t['must_be_0_or_1']} ({$t['got']} \"{$data['is_print_alone']}\")";
                }

                // ===== LOOKUP VALIDATION =====
                $categoryId = $categories[$data['category']] ?? null;
                if (!$categoryId) {
                    $available = !empty($categories) ? implode(', ', array_keys($categories)) : $t['none_create_first'];
                    $rowErrors[] = "\"{$fieldNames['category']}\" \"{$data['category']}\" {$t['not_found']}. {$t['available']}: {$available}";
                }

                $sampleId = $samples[$data['sample']] ?? null;
                if (!$sampleId) {
                    $available = !empty($samples) ? implode(', ', array_keys($samples)) : $t['none_create_first'];
                    $rowErrors[] = "\"{$fieldNames['sample']}\" \"{$data['sample']}\" {$t['not_found']}. {$t['available']}: {$available}";
                }

                $durationUnitId = null;
                if (!empty($data['duration_unit'])) {
                    $durationUnitId = $durationUnits[$data['duration_unit']] ?? null;
                    if (!$durationUnitId) {
                        $rowErrors[] = "\"{$fieldNames['duration_unit']}\" \"{$data['duration_unit']}\" {$t['invalid']}. {$t['must_be_one_of']}: " . implode(', ', array_keys($durationUnits));
                    }
                }

                // ===== CROSS-FIELD VALIDATION =====
                if (!empty($data['test_duration']) && empty($data['duration_unit'])) {
                    $rowErrors[] = "\"{$fieldNames['duration_unit']}\" {$t['required_when_duration']}";
                }

                // ===== DUPLICATE CHECKS (within file) =====
                $nameLower = strtolower($data['group_name']);
                $shortcutLower = strtolower($data['shortcut']);

                if (isset($seenNames[$nameLower])) {
                    $rowErrors[] = "{$t['duplicate_in_file']}: \"{$fieldNames['group_name']}\" \"{$data['group_name']}\" — {$t['same_as_row']} {$seenNames[$nameLower]}";
                }
                if (isset($seenShortcuts[$shortcutLower])) {
                    $rowErrors[] = "{$t['duplicate_in_file']}: \"{$fieldNames['shortcut']}\" \"{$data['shortcut']}\" — {$t['same_as_row']} {$seenShortcuts[$shortcutLower]}";
                }

                // ===== DUPLICATE CHECKS (in database) =====
                if (in_array($nameLower, $existingNames)) {
                    $rowErrors[] = "\"{$fieldNames['group_name']}\" \"{$data['group_name']}\" {$t['already_exists']}";
                }
                if (in_array($shortcutLower, $existingShortcuts)) {
                    $rowErrors[] = "\"{$fieldNames['shortcut']}\" \"{$data['shortcut']}\" {$t['already_exists']}";
                }

                // ===== COLLECT ERRORS OR CREATE =====
                if (!empty($rowErrors)) {
                    $errors[] = "{$t['row']} {$rowNum}: " . implode(' | ', $rowErrors);
                    $skipped++;
                    continue;
                }

                TestGroup::create([
                    'group_name' => $data['group_name'],
                    'shortcut' => $data['shortcut'],
                    'category_id_fk' => $categoryId,
                    'sample_id_fk' => $sampleId,
                    'original_price' => (int) $data['original_price'],
                    'for_customer_price' => !empty($data['for_customer_price']) ? (int) $data['for_customer_price'] : null,
                    'test_duration' => !empty($data['test_duration']) ? (int) $data['test_duration'] : null,
                    'duration_unit_id_fk' => $durationUnitId,
                    'precautions' => $data['precautions'] ?: null,
                    'is_print_alone' => $data['is_print_alone'] !== '' ? (int) $data['is_print_alone'] : 0,
                    'lab_id_fk' => $labId,
                ]);

                $seenNames[$nameLower] = $rowNum;
                $seenShortcuts[$shortcutLower] = $rowNum;
                $existingNames[] = $nameLower;
                $existingShortcuts[] = $shortcutLower;

                $created++;
            }

            if ($created === 0 && !empty($errors)) {
                DB::rollBack();

                return response()->json([
                    'message' => $t['no_items_imported'],
                    'created' => 0,
                    'skipped' => $skipped,
                    'errors' => $errors,
                ], 422);
            }

            DB::commit();

            ActivityLogController::storeActivity("استيراد {$created} مجموعة اختبار من ملف Excel", null);

            $msg = "{$t['success_imported']} {$created} {$t['items_word']}";
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
