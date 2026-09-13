<?php

namespace App\Http\Controllers;

use App\Http\Resources\CultureResource;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Culture;
use App\Models\DurationUnit;
use App\Models\Sample;
use App\Models\TestGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CultureController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('cultures view')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $query = Culture::with(['test_group', 'category', 'sample', 'duration_unit', 'attribute.resultType', 'price_list_rel.priceList', 'lab:id,name']);

        // Admin (role_id = 1) can see all cultures
        if ($user->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            $query->whereIn('lab_id_fk', $users_ids);
        }

        $cultures = $query->orderBy('created_at', 'desc')->get();
        if (count($cultures) == 0) {
            return response()->json($cultures, 200);
        }

        return response()->json(CultureResource::collection($cultures), 200);
    }

    public function show(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('cultures view')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $culture = Culture::with(['test_group', 'category', 'sample', 'duration_unit', 'attribute.resultType', 'price_list_rel.priceList', 'lab:id,name'])->find($request->id);
        if (!$culture) {
            return response()->json(['message' => 'Culture not found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser->role_id != 1 && $culture->lab_id_fk != $authUser->id && $culture->lab_id_fk != $authUser->creator_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(CultureResource::make($culture), 200);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('cultures create')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // validate request
        $request->validate([
            'name' => 'required|string|max:255',
            'precautions' => 'string|max:255',
            'result_comments' => 'array|max:255',
            'price' => 'nullable|integer',
            'category_id_fk' => 'required|integer|exists:categories,id',
            'test_group_id_fk' => 'nullable|integer|exists:test_groups,id',
            'sample_id_fk' => 'nullable|integer|exists:samples,id',
            'test_duration' => 'nullable|integer',
            'duration_unit_id_fk' => 'nullable|integer|exists:duration_units,id',
            'attributes' => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            $culture = Culture::create([
                'name' => $request->name,
                'precautions' => $request->precautions,
                'result_comments' => $request->input('result_comments'),
                'price' => $request->price,
                'category_id_fk' => $request->category_id_fk,
                'test_group_id_fk' => $request->test_group_id_fk,
                'sample_id_fk' => $request->sample_id_fk,
                'test_duration' => $request->test_duration,
                'duration_unit_id_fk' => $request->duration_unit_id_fk,
                'lab_id_fk' => Auth::user()->id,
            ]);
            if (!$culture) {
                DB::rollBack();
                return response()->json(['message' => 'Failed to create culture'], 500);
            }
            if (isset($request->attributes)) {
                foreach ($request->input('attributes') as $attribute) {
                    $attribute = Attribute::create([
                        'name' => $attribute['attribute_name'] ?? null,
                        'order' => $attribute['order'] ?? null,
                        'result_type_id_fk' => $attribute['result_type_id_fk'] ?? null,
                        'selection_type_options' => $attribute['selection_type_options'] ?? null,
                        'culture_id_fk' => $culture->id,
                    ]);
                    if (!$attribute) {
                        DB::rollBack();

                        return response()->json(['message' => 'Failed to create attribute'], 500);
                    }
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create culture: ' . $e->getMessage()], 500);
        }

        ActivityLogController::storeActivity('إنشاء زرع', $culture);

        return response()->json($culture, 200);
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('cultures edit')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // validate request
        $request->validate([
            'name' => 'required|string|max:255',
            'precautions' => 'string|max:255',
            'result_comments' => 'array|max:255',
            'price' => 'nullable|integer',
            'category_id_fk' => 'required|integer|exists:categories,id',
            'test_group_id_fk' => 'nullable|integer|exists:test_groups,id',
            'sample_id_fk' => 'nullable|integer|exists:samples,id',
            'test_duration' => 'nullable|integer',
            'duration_unit_id_fk' => 'nullable|integer|exists:duration_units,id',
            'attributes' => 'nullable|array',
        ]);
        $culture = Culture::find($request->id);
        if (!$culture) {
            return response()->json(['message' => 'Culture not found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser->role_id != 1 && $culture->lab_id_fk != $authUser->id && $culture->lab_id_fk != $authUser->creator_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $old_culture = new Culture($culture->toArray());
        DB::beginTransaction();
        try {
            $culture->update([
                'name' => $request->name,
                'precautions' => $request->precautions,
                'category_id_fk' => $request->category_id_fk,
                'result_comments' => $request->input('result_comments'),
                'price' => $request->price,
                'test_group_id_fk' => $request->test_group_id_fk,
                'sample_id_fk' => $request->sample_id_fk,
                'test_duration' => $request->test_duration,
                'duration_unit_id_fk' => $request->duration_unit_id_fk,
            ]);
            $culture->attribute()->delete();
            if (isset($request->attributes)) {
                foreach ($request->input('attributes') as $attribute) {
                    $attribute = Attribute::create([
                        'name' => $attribute['attribute_name'] ?? null,
                        'order' => $attribute['order'] ?? null,
                        'result_type_id_fk' => $attribute['result_type_id_fk'] ?? null,
                        'selection_type_options' => $attribute['selection_type_options'] ?? null,
                        'culture_id_fk' => $culture->id,
                    ]);
                    if (!$attribute) {
                        DB::rollBack();

                        return response()->json(['message' => 'Failed to create attribute'], 500);
                    }
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update culture: ' . $e->getMessage()], 500);
        }

        ActivityLogController::updateActivity('تعديل زرع', $old_culture, $culture);

        return response()->json($culture, 200);
    }

    public function destroy(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('cultures delete')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $culture = Culture::find($request->id);
        if (!$culture) {
            return response()->json(['message' => 'Culture not found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser->role_id != 1 && $culture->lab_id_fk != $authUser->id && $culture->lab_id_fk != $authUser->creator_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        ActivityLogController::deleteActivity('حذف زرع', $culture);
        $culture->delete();

        return response()->json(['message' => 'Culture deleted successfully'], 200);
    }

    /**
     * Download the Excel import template
     */
    public function downloadTemplate()
    {
        $path = storage_path('app/templates/cultures_import_template.xlsx');

        if (!file_exists($path)) {
            return response()->json(['message' => 'Template file not found'], 404);
        }

        return response()->download($path, 'cultures_import_template.xlsx');
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
                'must_be_numeric' => 'يجب أن يكون رقمًا',
                'not_found' => 'غير موجود',
                'duplicate_in_file' => 'مكرر في الملف',
                'same_as_row' => 'نفس الصف',
                'already_exists' => 'موجود مسبقاً في زروعاتك',
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
                'no_items_imported' => 'لم يتم استيراد أي زروعات بسبب أخطاء التحقق',
                'success_imported' => 'تم استيراد',
                'items_word' => 'زرع بنجاح',
                'rows_skipped' => 'صف تم تخطيه',
                'invalid_file' => 'ملف Excel غير صالح',
                'invalid_file_detail' => 'لا يمكن قراءة الملف. تأكد من أنه ملف .xlsx أو .xls صالح.',
                'import_failed' => 'فشل الاستيراد',
                'field_name' => 'اسم الزرع',
                'field_category' => 'الفئة',
                'field_test_group' => 'المجموعة',
                'field_sample' => 'العينة',
                'field_price' => 'السعر',
                'field_test_duration' => 'مدة الفحص',
                'field_duration_unit' => 'وحدة المدة',
                'field_precautions' => 'الاحتياطات',
            ];
        }

        return [
            'row' => 'Row',
            'required' => 'is required',
            'must_not_exceed' => 'must not exceed',
            'characters' => 'characters',
            'got' => 'got',
            'must_be_numeric' => 'must be a number',
            'not_found' => 'not found',
            'duplicate_in_file' => 'Duplicate in file',
            'same_as_row' => 'same as row',
            'already_exists' => 'already exists in your cultures',
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
            'no_items_imported' => 'No cultures were imported due to validation errors',
            'success_imported' => 'Successfully imported',
            'items_word' => 'cultures',
            'rows_skipped' => 'rows skipped',
            'invalid_file' => 'Invalid Excel file',
            'invalid_file_detail' => 'The file could not be read. Please ensure it is a valid .xlsx or .xls file.',
            'import_failed' => 'Import failed',
            'field_name' => 'name',
            'field_category' => 'category',
            'field_test_group' => 'test_group',
            'field_sample' => 'sample',
            'field_price' => 'price',
            'field_test_duration' => 'test_duration',
            'field_duration_unit' => 'duration_unit',
            'field_precautions' => 'precautions',
        ];
    }

    /**
     * Import cultures from an Excel file
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
            $requiredColumns = ['name', 'category'];
            $missingColumns = array_diff($requiredColumns, $headerValues);

            if (!empty($missingColumns)) {
                return response()->json([
                    'message' => $t['missing_columns'],
                    'created' => 0,
                    'skipped' => 0,
                    'errors' => [$t['missing_columns_detail'] . ': ' . implode(', ', $missingColumns) . '. ' . $t['use_template']],
                ], 422);
            }

            // Preload lookup tables
            $categories = Category::where('lab_id_fk', $labId)->get()->keyBy(fn($c) => strtolower(trim($c->name)));
            $testGroups = TestGroup::where('lab_id_fk', $labId)->get()->keyBy(fn($g) => strtolower(trim($g->group_name)));
            $samples = Sample::where('lab_id_fk', $labId)->get()->keyBy(fn($s) => strtolower(trim($s->sample_name)));
            $durationUnits = DurationUnit::all()->keyBy(fn($d) => strtolower(trim($d->unit)));

            // Track duplicates within the file
            $seenNames = [];

            // Preload existing culture names for this lab
            $existingNames = Culture::where('lab_id_fk', $labId)
                ->pluck('name')
                ->map(fn($n) => strtolower(trim($n)))
                ->toArray();

            $created = 0;
            $skipped = 0;
            $errors = [];
            $maxRows = 500;

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

                $name = isset($row['A']) ? trim((string) $row['A']) : '';
                $categoryName = isset($row['B']) ? trim((string) $row['B']) : '';
                $testGroupName = isset($row['C']) ? trim((string) $row['C']) : '';
                $sampleName = isset($row['D']) ? trim((string) $row['D']) : '';
                $priceRaw = isset($row['E']) ? trim((string) $row['E']) : '';
                $testDurationRaw = isset($row['F']) ? trim((string) $row['F']) : '';
                $durationUnitName = isset($row['G']) ? trim((string) $row['G']) : '';
                $precautions = isset($row['H']) ? trim((string) $row['H']) : '';

                // Skip empty rows
                if ($name === '' && $categoryName === '') {
                    continue;
                }

                // ===== REQUIRED FIELD VALIDATION =====
                if ($name === '') {
                    $rowErrors[] = "\"{$t['field_name']}\" {$t['required']}";
                }
                if ($categoryName === '') {
                    $rowErrors[] = "\"{$t['field_category']}\" {$t['required']}";
                }

                // ===== STRING LENGTH VALIDATION =====
                if (mb_strlen($name) > 255) {
                    $rowErrors[] = "\"{$t['field_name']}\" {$t['must_not_exceed']} 255 {$t['characters']} ({$t['got']} " . mb_strlen($name) . ")";
                }
                if (mb_strlen($precautions) > 255) {
                    $rowErrors[] = "\"{$t['field_precautions']}\" {$t['must_not_exceed']} 255 {$t['characters']} ({$t['got']} " . mb_strlen($precautions) . ")";
                }

                // ===== NUMERIC VALIDATION =====
                $price = null;
                if ($priceRaw !== '') {
                    if (!is_numeric($priceRaw)) {
                        $rowErrors[] = "\"{$t['field_price']}\" {$t['must_be_numeric']}";
                    } else {
                        $price = (int) $priceRaw;
                    }
                }

                $testDuration = null;
                if ($testDurationRaw !== '') {
                    if (!is_numeric($testDurationRaw)) {
                        $rowErrors[] = "\"{$t['field_test_duration']}\" {$t['must_be_numeric']}";
                    } else {
                        $testDuration = (int) $testDurationRaw;
                    }
                }

                // ===== LOOKUP RESOLUTION =====
                $categoryId = null;
                if ($categoryName !== '') {
                    $cat = $categories->get(strtolower($categoryName));
                    if (!$cat) {
                        $rowErrors[] = "\"{$t['field_category']}\" \"{$categoryName}\" {$t['not_found']}";
                    } else {
                        $categoryId = $cat->id;
                    }
                }

                $testGroupId = null;
                if ($testGroupName !== '') {
                    $grp = $testGroups->get(strtolower($testGroupName));
                    if (!$grp) {
                        $rowErrors[] = "\"{$t['field_test_group']}\" \"{$testGroupName}\" {$t['not_found']}";
                    } else {
                        $testGroupId = $grp->id;
                    }
                }

                $sampleId = null;
                if ($sampleName !== '') {
                    $smp = $samples->get(strtolower($sampleName));
                    if (!$smp) {
                        $rowErrors[] = "\"{$t['field_sample']}\" \"{$sampleName}\" {$t['not_found']}";
                    } else {
                        $sampleId = $smp->id;
                    }
                }

                $durationUnitId = null;
                if ($durationUnitName !== '') {
                    $du = $durationUnits->get(strtolower($durationUnitName));
                    if (!$du) {
                        $rowErrors[] = "\"{$t['field_duration_unit']}\" \"{$durationUnitName}\" {$t['not_found']}";
                    } else {
                        $durationUnitId = $du->id;
                    }
                }

                // ===== DUPLICATE CHECKS (within file) =====
                if ($name !== '') {
                    $nameLower = strtolower($name);
                    if (isset($seenNames[$nameLower])) {
                        $rowErrors[] = "{$t['duplicate_in_file']}: \"{$t['field_name']}\" \"{$name}\" — {$t['same_as_row']} {$seenNames[$nameLower]}";
                    }

                    // ===== DUPLICATE CHECKS (in database) =====
                    if (in_array($nameLower, $existingNames)) {
                        $rowErrors[] = "\"{$t['field_name']}\" \"{$name}\" {$t['already_exists']}";
                    }
                }

                // ===== COLLECT ERRORS OR CREATE =====
                if (!empty($rowErrors)) {
                    $errors[] = "{$t['row']} {$rowNum}: " . implode(' | ', $rowErrors);
                    $skipped++;
                    continue;
                }

                Culture::create([
                    'name' => $name,
                    'category_id_fk' => $categoryId,
                    'test_group_id_fk' => $testGroupId,
                    'sample_id_fk' => $sampleId,
                    'price' => $price,
                    'test_duration' => $testDuration,
                    'duration_unit_id_fk' => $durationUnitId,
                    'precautions' => $precautions !== '' ? $precautions : null,
                    'lab_id_fk' => $labId,
                ]);

                $seenNames[$nameLower] = $rowNum;
                $existingNames[] = $nameLower;
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

            ActivityLogController::storeActivity("استيراد {$created} زرع من ملف Excel", null);

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
