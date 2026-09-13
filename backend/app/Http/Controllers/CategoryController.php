<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('categories view')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $query = Category::with(['testGroups', 'lab']);

        // Admin (role_id = 1) can see all categories
        if ($user->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            $query->whereIn('lab_id_fk', $users_ids);
        }

        $categories = $query->orderBy('created_at', 'desc')->get();
        $categories = $categories->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'lab' => $category->lab?->name,
                'test_groups' => $category->testGroups,
            ];
        });

        return response()->json($categories);
    }

    public function show(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('categories view')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $category = Category::with(['testGroups', 'lab'])->find($request->id);
        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser->role_id != 1 && $category->lab_id_fk != $authUser->id && $category->lab_id_fk != $authUser->creator_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $category = [
            'id' => $category->id,
            'name' => $category->name,
            'lab' => $category->lab?->name,
            'test_groups' => $category->testGroups,
        ];

        return response()->json($category);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('categories create')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validatedData = $request->validate([
            'name' => 'required|string',
        ]);
        $category = Category::create([
            'name' => $validatedData['name'],
            'lab_id_fk' => Auth::user()->id,
        ]);
        ActivityLogController::storeActivity('إنشاء فئة', $category);

        return response()->json($category, 201);
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('categories edit')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $category = Category::findOrFail($request->id);

        $authUser = Auth::user();
        if ($authUser->role_id != 1 && $category->lab_id_fk != $authUser->id && $category->lab_id_fk != $authUser->creator_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $old_category = new Category($category->toArray());
        $validatedData = $request->validate([
            'name' => 'required|string',
        ]);
        $category->update([
            'name' => $validatedData['name'],
        ]);

        ActivityLogController::updateActivity('تعديل فئة', $category, $old_category);

        return response()->json($category);
    }

    public function destroy(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('categories delete')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $category = Category::findOrFail($request->id);

        $authUser = Auth::user();
        if ($authUser->role_id != 1 && $category->lab_id_fk != $authUser->id && $category->lab_id_fk != $authUser->creator_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $category->delete();

        ActivityLogController::deleteActivity('حذف فئة', $category);

        return response()->json(['message' => 'Category deleted successfully']);
    }

    /**
     * Download the Excel import template
     */
    public function downloadTemplate()
    {
        $path = storage_path('app/templates/categories_import_template.xlsx');

        if (!file_exists($path)) {
            return response()->json(['message' => 'Template file not found'], 404);
        }

        return response()->download($path, 'categories_import_template.xlsx');
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
                'duplicate_in_file' => 'مكرر في الملف',
                'same_as_row' => 'نفس الصف',
                'already_exists' => 'موجود مسبقاً في فئاتك',
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
                'no_items_imported' => 'لم يتم استيراد أي فئات بسبب أخطاء التحقق',
                'success_imported' => 'تم استيراد',
                'items_word' => 'فئة بنجاح',
                'rows_skipped' => 'صف تم تخطيه',
                'invalid_file' => 'ملف Excel غير صالح',
                'invalid_file_detail' => 'لا يمكن قراءة الملف. تأكد من أنه ملف .xlsx أو .xls صالح.',
                'import_failed' => 'فشل الاستيراد',
                'field_name' => 'الاسم',
            ];
        }

        return [
            'row' => 'Row',
            'required' => 'is required',
            'must_not_exceed' => 'must not exceed',
            'characters' => 'characters',
            'got' => 'got',
            'duplicate_in_file' => 'Duplicate in file',
            'same_as_row' => 'same as row',
            'already_exists' => 'already exists in your categories',
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
            'no_items_imported' => 'No categories were imported due to validation errors',
            'success_imported' => 'Successfully imported',
            'items_word' => 'categories',
            'rows_skipped' => 'rows skipped',
            'invalid_file' => 'Invalid Excel file',
            'invalid_file_detail' => 'The file could not be read. Please ensure it is a valid .xlsx or .xls file.',
            'import_failed' => 'Import failed',
            'field_name' => 'name',
        ];
    }

    /**
     * Import categories from an Excel file
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
            if (!in_array('name', $headerValues)) {
                return response()->json([
                    'message' => $t['missing_columns'],
                    'created' => 0,
                    'skipped' => 0,
                    'errors' => [$t['missing_columns_detail'] . ': name. ' . $t['use_template']],
                ], 422);
            }

            // Track duplicates within the file
            $seenNames = [];

            // Preload existing category names for this lab
            $existingNames = Category::where('lab_id_fk', $labId)
                ->pluck('name')
                ->map(fn($n) => strtolower(trim($n)))
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

                $name = isset($row['A']) ? trim((string) $row['A']) : '';

                // Skip empty rows
                if ($name === '') {
                    continue;
                }

                // ===== REQUIRED FIELD =====
                if (empty($name)) {
                    $rowErrors[] = "\"{$t['field_name']}\" {$t['required']}";
                }

                // ===== STRING LENGTH VALIDATION =====
                if (mb_strlen($name) > 255) {
                    $rowErrors[] = "\"{$t['field_name']}\" {$t['must_not_exceed']} 255 {$t['characters']} ({$t['got']} " . mb_strlen($name) . ")";
                }

                // ===== DUPLICATE CHECKS (within file) =====
                $nameLower = strtolower($name);
                if (isset($seenNames[$nameLower])) {
                    $rowErrors[] = "{$t['duplicate_in_file']}: \"{$t['field_name']}\" \"{$name}\" — {$t['same_as_row']} {$seenNames[$nameLower]}";
                }

                // ===== DUPLICATE CHECKS (in database) =====
                if (in_array($nameLower, $existingNames)) {
                    $rowErrors[] = "\"{$t['field_name']}\" \"{$name}\" {$t['already_exists']}";
                }

                // ===== COLLECT ERRORS OR CREATE =====
                if (!empty($rowErrors)) {
                    $errors[] = "{$t['row']} {$rowNum}: " . implode(' | ', $rowErrors);
                    $skipped++;
                    continue;
                }

                Category::create([
                    'name' => $name,
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

            ActivityLogController::storeActivity("استيراد {$created} فئة من ملف Excel", null);

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
