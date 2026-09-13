<?php

namespace App\Http\Controllers;

use App\Models\Antibiotics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AntibioticsController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('antibiotics view')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $query = Antibiotics::with('lab');

        if ($user->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            $query->whereIn('lab_id', $users_ids);
        }

        $antibiotics = $query->orderBy('created_at', 'desc')->get();
        $antibiotics = $antibiotics->map(function ($antibiotic) {
            return [
                'id' => $antibiotic->id,
                'short_name' => $antibiotic->short_name,
                'common_name' => $antibiotic->common_name,
                'scientific_name' => $antibiotic->scientific_name,
                'lab' => $antibiotic->lab?->name,
            ];
        });

        return response()->json($antibiotics);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('antibiotics create')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'short_name' => 'required|string|max:255',
            'common_name' => 'required|string|max:255',
            'scientific_name' => 'required|string|max:255',
        ]);
        $antibiotics = Antibiotics::create([
            'short_name' => $request->short_name,
            'common_name' => $request->common_name,
            'scientific_name' => $request->scientific_name,
            'lab_id' => Auth::user()->id,
        ]);
        if (! $antibiotics) {
            return response()->json(['message' => 'Antibiotics not created'], 500);
        }
        ActivityLogController::storeActivity('خزن مضاد حيوي', $antibiotics);

        return response()->json($antibiotics, 201);
    }

    public function show(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('antibiotics view')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $antibiotics = Antibiotics::with('lab:id,name')->find($request->id);
        if (! $antibiotics) {
            return response()->json(['message' => 'Antibiotics not found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser->role_id != 1 && $antibiotics->lab_id != $authUser->id && $antibiotics->lab_id != $authUser->creator_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($antibiotics);
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('antibiotics edit')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'id' => 'required|exists:antibiotics,id',
            'short_name' => 'required|string|max:255',
            'common_name' => 'required|string|max:255',
            'scientific_name' => 'required|string|max:255',
        ]);
        $antibiotics = Antibiotics::find($request->id);
        if (! $antibiotics) {
            return response()->json(['message' => 'Antibiotics not found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser->role_id != 1 && $antibiotics->lab_id != $authUser->id && $antibiotics->lab_id != $authUser->creator_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $old_antibiotics = new Antibiotics($antibiotics->toArray());
        $antibiotics->update([
            'short_name' => $request->short_name,
            'common_name' => $request->common_name,
            'scientific_name' => $request->scientific_name,
        ]);
        ActivityLogController::updateActivity('تعديل مضاد حيوي', $antibiotics, $old_antibiotics);

        return response()->json($antibiotics);
    }

    public function destroy(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('antibiotics delete')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $antibiotics = Antibiotics::find($request->id);
        if (! $antibiotics) {
            return response()->json(['message' => 'Antibiotics not found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser->role_id != 1 && $antibiotics->lab_id != $authUser->id && $antibiotics->lab_id != $authUser->creator_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        ActivityLogController::deleteActivity('حذف مضاد حيوي', $antibiotics);
        $antibiotics->delete();

        return response()->json(['message' => 'Antibiotics deleted successfully']);
    }

    /**
     * Download the Excel import template
     */
    public function downloadTemplate()
    {
        $path = storage_path('app/templates/antibiotics_import_template.xlsx');

        if (!file_exists($path)) {
            return response()->json(['message' => 'Template file not found'], 404);
        }

        return response()->download($path, 'antibiotics_import_template.xlsx');
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
                'already_exists' => 'موجود مسبقاً في مضاداتك الحيوية',
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
                'no_items_imported' => 'لم يتم استيراد أي مضادات حيوية بسبب أخطاء التحقق',
                'success_imported' => 'تم استيراد',
                'items_word' => 'مضاد حيوي بنجاح',
                'rows_skipped' => 'صف تم تخطيه',
                'invalid_file' => 'ملف Excel غير صالح',
                'invalid_file_detail' => 'لا يمكن قراءة الملف. تأكد من أنه ملف .xlsx أو .xls صالح.',
                'import_failed' => 'فشل الاستيراد',
                'field_short_name' => 'الاسم المختصر',
                'field_common_name' => 'الاسم الشائع',
                'field_scientific_name' => 'الاسم العلمي',
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
            'already_exists' => 'already exists in your antibiotics',
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
            'no_items_imported' => 'No antibiotics were imported due to validation errors',
            'success_imported' => 'Successfully imported',
            'items_word' => 'antibiotics',
            'rows_skipped' => 'rows skipped',
            'invalid_file' => 'Invalid Excel file',
            'invalid_file_detail' => 'The file could not be read. Please ensure it is a valid .xlsx or .xls file.',
            'import_failed' => 'Import failed',
            'field_short_name' => 'short_name',
            'field_common_name' => 'common_name',
            'field_scientific_name' => 'scientific_name',
        ];
    }

    /**
     * Import antibiotics from an Excel file
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
            $requiredColumns = ['short_name', 'common_name', 'scientific_name'];
            $missingColumns = array_diff($requiredColumns, $headerValues);

            if (!empty($missingColumns)) {
                return response()->json([
                    'message' => $t['missing_columns'],
                    'created' => 0,
                    'skipped' => 0,
                    'errors' => [$t['missing_columns_detail'] . ': ' . implode(', ', $missingColumns) . '. ' . $t['use_template']],
                ], 422);
            }

            // Track duplicates within the file
            $seenNames = [];

            // Preload existing scientific names for this lab
            $existingNames = Antibiotics::where('lab_id', $labId)
                ->pluck('scientific_name')
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

                $shortName = isset($row['A']) ? trim((string) $row['A']) : '';
                $commonName = isset($row['B']) ? trim((string) $row['B']) : '';
                $scientificName = isset($row['C']) ? trim((string) $row['C']) : '';

                // Skip empty rows
                if ($shortName === '' && $commonName === '' && $scientificName === '') {
                    continue;
                }

                // ===== REQUIRED FIELD VALIDATION =====
                if ($shortName === '') {
                    $rowErrors[] = "\"{$t['field_short_name']}\" {$t['required']}";
                }
                if ($commonName === '') {
                    $rowErrors[] = "\"{$t['field_common_name']}\" {$t['required']}";
                }
                if ($scientificName === '') {
                    $rowErrors[] = "\"{$t['field_scientific_name']}\" {$t['required']}";
                }

                // ===== STRING LENGTH VALIDATION =====
                if (mb_strlen($shortName) > 255) {
                    $rowErrors[] = "\"{$t['field_short_name']}\" {$t['must_not_exceed']} 255 {$t['characters']} ({$t['got']} " . mb_strlen($shortName) . ")";
                }
                if (mb_strlen($commonName) > 255) {
                    $rowErrors[] = "\"{$t['field_common_name']}\" {$t['must_not_exceed']} 255 {$t['characters']} ({$t['got']} " . mb_strlen($commonName) . ")";
                }
                if (mb_strlen($scientificName) > 255) {
                    $rowErrors[] = "\"{$t['field_scientific_name']}\" {$t['must_not_exceed']} 255 {$t['characters']} ({$t['got']} " . mb_strlen($scientificName) . ")";
                }

                // ===== DUPLICATE CHECKS (within file) =====
                if ($scientificName !== '') {
                    $nameLower = strtolower($scientificName);
                    if (isset($seenNames[$nameLower])) {
                        $rowErrors[] = "{$t['duplicate_in_file']}: \"{$t['field_scientific_name']}\" \"{$scientificName}\" — {$t['same_as_row']} {$seenNames[$nameLower]}";
                    }

                    // ===== DUPLICATE CHECKS (in database) =====
                    if (in_array($nameLower, $existingNames)) {
                        $rowErrors[] = "\"{$t['field_scientific_name']}\" \"{$scientificName}\" {$t['already_exists']}";
                    }
                }

                // ===== COLLECT ERRORS OR CREATE =====
                if (!empty($rowErrors)) {
                    $errors[] = "{$t['row']} {$rowNum}: " . implode(' | ', $rowErrors);
                    $skipped++;
                    continue;
                }

                Antibiotics::create([
                    'short_name' => $shortName,
                    'common_name' => $commonName,
                    'scientific_name' => $scientificName,
                    'lab_id' => $labId,
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

            ActivityLogController::storeActivity("استيراد {$created} مضاد حيوي من ملف Excel", null);

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
