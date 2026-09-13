<?php

namespace App\Http\Controllers;

use App\Models\AnswerType;
use App\Models\PatientQuestion;
use App\Models\TestQuestionRel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PatientQuestionController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('test questions view')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $query = PatientQuestion::with(['answerType', 'lab']);

        // Admin (role_id = 1) can see all patient questions
        if ($user->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            $query->whereIn('lab_id_fk', $users_ids);
        }

        $questions = $query->orderBy('created_at', 'desc')->get();
        $questions = $questions->map(function ($question) {
            return [
                'id' => $question->id,
                'lab' => $question->lab?->name,
                'question' => $question->question,
                'answer_type' => $question->answerType->answer,
                'answer_type_id' => $question->answer_type_id_fk,
                'answer_type_selection_values' => $question->answer_type_selection_values,
                'created_at' => $question->created_at,
                'updated_at' => $question->updated_at,
            ];
        });

        return response()->json($questions);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('test questions create')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'question' => 'required|string',
            'answer_type_id_fk' => 'required|integer',
            'answer_type_selection_values' => 'required|array',
        ]);
        $patient_question = PatientQuestion::create([
            'question' => $request->question,
            'answer_type_id_fk' => $request->answer_type_id_fk,
            'answer_type_selection_values' => $request->input('answer_type_selection_values'),
            'lab_id_fk' => Auth::user()->id,
        ]);
        if ($patient_question) {
            ActivityLogController::storeActivity('إنشاء سؤال جديد', $patient_question);

            return response()->json($patient_question);

        } else {
            return response()->json(['message' => 'Something went wrong'], 500);
        }

    }

    public function show(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('test questions view')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $questions = PatientQuestion::with('answerType', 'lab')->find($request->id);
        if (!$questions) {
            return response()->json(['message' => 'Question not found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (!in_array($questions->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        if ($questions) {
            $questions = [
                'id' => $questions->id,
                'question' => $questions->question,
                'answer_type' => $questions->answerType->answer,
                'answer_type_id' => $questions->answer_type_id_fk,
                'answer_type_selection_values' => $questions->answer_type_selection_values,
                'lab' => $questions->lab?->name,
            ];

            return response()->json($questions);
        } else {
            return response()->json(['message' => 'Question not found'], 404);
        }
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('test questions edit')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'question' => 'required|string',
            'answer_type_id_fk' => 'required|integer',
            'answer_type_selection_values' => 'required|array',
        ]);
        $patient_question = PatientQuestion::find($request->id);
        if (!$patient_question) {
            return response()->json(['message' => 'Question not found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (!in_array($patient_question->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        if ($patient_question) {
            $old_data = new PatientQuestion($patient_question->toArray());
            $patient_question->update([
                'question' => $request->question,
                'answer_type_id_fk' => $request->answer_type_id_fk,
                'answer_type_selection_values' => $request->input('answer_type_selection_values'),
            ]);
            ActivityLogController::updateActivity('تعديل سؤال', $old_data, $patient_question);

            return response()->json($patient_question);
        } else {
            return response()->json(['message' => 'Question not found'], 404);
        }
    }

    public function destroy(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('test questions delete')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $patient_question = PatientQuestion::find($request->id);
        if (!$patient_question) {
            return response()->json(['message' => 'Question not found'], 404);
        }

        $authUser = Auth::user();
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();
            if (!in_array($patient_question->lab_id_fk, $users_ids)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        if ($patient_question) {
            $test_question_rel = TestQuestionRel::where('question_id_fk', $request->id)->get();
            foreach ($test_question_rel as $value) {
                $value->delete();
            }
            $patient_question->delete();
            ActivityLogController::deleteActivity('حذف سؤال', $patient_question);

            return response()->json(['message' => 'Question deleted successfully']);
        } else {
            return response()->json(['message' => 'Question not found'], 404);
        }
    }

    /**
     * Download the Excel import template
     */
    public function downloadTemplate()
    {
        $path = storage_path('app/templates/test_questions_import_template.xlsx');

        if (!file_exists($path)) {
            return response()->json(['message' => 'Template file not found'], 404);
        }

        return response()->download($path, 'test_questions_import_template.xlsx');
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
                'not_found' => 'غير موجود',
                'duplicate_in_file' => 'مكرر في الملف',
                'same_as_row' => 'نفس الصف',
                'already_exists' => 'موجود مسبقاً في أسئلتك',
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
                'no_items_imported' => 'لم يتم استيراد أي أسئلة بسبب أخطاء التحقق',
                'success_imported' => 'تم استيراد',
                'items_word' => 'سؤال بنجاح',
                'rows_skipped' => 'صف تم تخطيه',
                'invalid_file' => 'ملف Excel غير صالح',
                'invalid_file_detail' => 'لا يمكن قراءة الملف. تأكد من أنه ملف .xlsx أو .xls صالح.',
                'import_failed' => 'فشل الاستيراد',
                'field_question' => 'السؤال',
                'field_answer_type' => 'نوع الإجابة',
                'field_selection_values' => 'قيم الاختيار',
                'selection_values_required' => 'قيم الاختيار مطلوبة عندما يكون نوع الإجابة اختيار',
            ];
        }

        return [
            'row' => 'Row',
            'required' => 'is required',
            'must_not_exceed' => 'must not exceed',
            'characters' => 'characters',
            'got' => 'got',
            'not_found' => 'not found',
            'duplicate_in_file' => 'Duplicate in file',
            'same_as_row' => 'same as row',
            'already_exists' => 'already exists in your questions',
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
            'no_items_imported' => 'No questions were imported due to validation errors',
            'success_imported' => 'Successfully imported',
            'items_word' => 'questions',
            'rows_skipped' => 'rows skipped',
            'invalid_file' => 'Invalid Excel file',
            'invalid_file_detail' => 'The file could not be read. Please ensure it is a valid .xlsx or .xls file.',
            'import_failed' => 'Import failed',
            'field_question' => 'question',
            'field_answer_type' => 'answer_type',
            'field_selection_values' => 'answer_type_selection_values',
            'selection_values_required' => 'Selection values are required when answer type is Selection',
        ];
    }

    /**
     * Import test questions from an Excel file
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
            $requiredColumns = ['question', 'answer_type'];
            $missingColumns = array_diff($requiredColumns, $headerValues);

            if (!empty($missingColumns)) {
                return response()->json([
                    'message' => $t['missing_columns'],
                    'created' => 0,
                    'skipped' => 0,
                    'errors' => [$t['missing_columns_detail'] . ': ' . implode(', ', $missingColumns) . '. ' . $t['use_template']],
                ], 422);
            }

            // Preload answer types
            $answerTypes = AnswerType::all()->keyBy(fn($a) => strtolower(trim($a->answer)));

            // Track duplicates within the file
            $seenQuestions = [];

            // Preload existing questions for this lab
            $existingQuestions = PatientQuestion::where('lab_id_fk', $labId)
                ->pluck('question')
                ->map(fn($q) => strtolower(trim($q)))
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

                $question = isset($row['A']) ? trim((string) $row['A']) : '';
                $answerTypeName = isset($row['B']) ? trim((string) $row['B']) : '';
                $selectionValuesRaw = isset($row['C']) ? trim((string) $row['C']) : '';

                // Skip empty rows
                if ($question === '' && $answerTypeName === '') {
                    continue;
                }

                // ===== REQUIRED FIELD VALIDATION =====
                if ($question === '') {
                    $rowErrors[] = "\"{$t['field_question']}\" {$t['required']}";
                }
                if ($answerTypeName === '') {
                    $rowErrors[] = "\"{$t['field_answer_type']}\" {$t['required']}";
                }

                // ===== STRING LENGTH VALIDATION =====
                if (mb_strlen($question) > 500) {
                    $rowErrors[] = "\"{$t['field_question']}\" {$t['must_not_exceed']} 500 {$t['characters']} ({$t['got']} " . mb_strlen($question) . ")";
                }

                // ===== LOOKUP RESOLUTION =====
                $answerTypeId = null;
                $isSelectionType = false;
                if ($answerTypeName !== '') {
                    $at = $answerTypes->get(strtolower($answerTypeName));
                    if (!$at) {
                        $rowErrors[] = "\"{$t['field_answer_type']}\" \"{$answerTypeName}\" {$t['not_found']}";
                    } else {
                        $answerTypeId = $at->id;
                        $isSelectionType = strtolower($at->answer) === 'selection';
                    }
                }

                // ===== SELECTION VALUES VALIDATION =====
                $selectionValues = [''];
                if ($selectionValuesRaw !== '') {
                    $selectionValues = array_map('trim', explode(',', $selectionValuesRaw));
                    $selectionValues = array_filter($selectionValues, fn($v) => $v !== '');
                    if (empty($selectionValues)) {
                        $selectionValues = [''];
                    }
                }

                if ($isSelectionType && ($selectionValuesRaw === '' || $selectionValues === [''])) {
                    $rowErrors[] = $t['selection_values_required'];
                }

                // ===== DUPLICATE CHECKS (within file) =====
                if ($question !== '') {
                    $questionLower = strtolower($question);
                    if (isset($seenQuestions[$questionLower])) {
                        $rowErrors[] = "{$t['duplicate_in_file']}: \"{$t['field_question']}\" \"{$question}\" — {$t['same_as_row']} {$seenQuestions[$questionLower]}";
                    }

                    // ===== DUPLICATE CHECKS (in database) =====
                    if (in_array($questionLower, $existingQuestions)) {
                        $rowErrors[] = "\"{$t['field_question']}\" \"{$question}\" {$t['already_exists']}";
                    }
                }

                // ===== COLLECT ERRORS OR CREATE =====
                if (!empty($rowErrors)) {
                    $errors[] = "{$t['row']} {$rowNum}: " . implode(' | ', $rowErrors);
                    $skipped++;
                    continue;
                }

                PatientQuestion::create([
                    'question' => $question,
                    'answer_type_id_fk' => $answerTypeId,
                    'answer_type_selection_values' => $selectionValues,
                    'lab_id_fk' => $labId,
                ]);

                $seenQuestions[$questionLower] = $rowNum;
                $existingQuestions[] = $questionLower;
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

            ActivityLogController::storeActivity("استيراد {$created} سؤال من ملف Excel", null);

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
