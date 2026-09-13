<?php

namespace App\Http\Controllers;

use App\Http\Resources\PatientResource;
use App\Models\Patient;
use App\Models\User;
use App\Traits\SecureFileUpload;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PatientsController extends Controller
{
    use SecureFileUpload;
    // public function generateBarcode()
    // {
    //     return Patient::generateUniqueBarcode();
    // }
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('patients view')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $query = Patient::with([
            'contract',
            'title',
            'nationality',
            'lab',
            'gender:id,gender_type',
            'ageUnit:id,unit_name',
            'user.role',
        ]);

        // Admin (role_id = 1) can see all patients
        if ($user->role_id == 1) {
            $patients = $query->orderBy('created_at', 'desc')->get();
        } else {
            $users_ids = $this->getTenantUserIds();
            $patients = $query->where(function ($q) use ($users_ids) {
                $q->whereIn('creator_id', $users_ids)->orWhereIn('parent_id', $users_ids);
            })
                ->orderBy('created_at', 'desc')
                ->get();
        }

        if ($patients->isEmpty()) {
            return response()->json([], 200);
        }

        $patient_data = PatientResource::collection($patients);

        // Admin or Lab Owner (role_id = 1 or 2) can see all their patients
        if ($user->role_id == 1 || $user->role_id == 2) {
            return response()->json($patient_data, 200);
        } elseif ($user->role_id == 4) {
            $filtered_data = $patient_data->where('belong_to_id', $user->id)->values();

            return response()->json($filtered_data, 200);
        } else {
            return response()->json(['message' => 'unauthorized to access this resource'], 404);
        }
    }

    public function show(Request $request)
    {
        $authUser = Auth::user();
        if (!$authUser->hasPermissionTo('patients view')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $patient = Patient::where('id', $request->id)
            ->with([
                'user' => function ($q): void {
                    $q->select('id', 'name', 'email', 'phone_number', 'image', 'address', 'role_id');
                },
                'user.role:id,name',
                'lab',
                'contract',
                'title',
                'nationality',
                'gender:id,gender_type',
                'ageUnit:id,unit_name',
            ])
            ->select([
                'id',
                'code',
                'title_id_fk',
                'barcode',
                'parent_id',
                'lab_card',
                'nationality_id_fk',
                'dob',
                'gender_id_fk',
                'age',
                'age_unit_id_fk',
                'passport_no',
                'national_id_no',
                'contract_id_fk',
                'user_id',
                'creator_id',
            ])
            ->first();

        if (!$patient) {
            return response()->json(['message' => 'Patient not found'], 404);
        }

        // SECURITY: Authorization check - verify user has access to this patient
        if ($authUser->role_id !== 1) { // Not admin
            if ($patient->creator_id !== $authUser->id && $patient->parent_id !== $authUser->id) {
                return response()->json(['message' => 'Unauthorized access to this patient'], 403);
            }
        }

        $patient_data = PatientResource::make($patient);

        return response()->json($patient_data);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('patients create')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Clean up null string values before validation
        $this->convertNullStrings($request, [
            'email',
            'phone_number',
            'contract_id_fk',
            'address',
            'nationality_id_fk',
            'dob',
            'passport_no',
            'national_id_no'
        ]);

        // SECURITY: Proper image validation
        $validatedData = $request->validate([
            'title_id_fk' => 'required|exists:titles,id',
            'name' => 'required|string',
            'email' => 'nullable|email',
            'phone_number' => ['nullable', 'string', 'regex:/^\+964[0-9]{10}$/'],
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'contract_id_fk' => 'nullable|exists:contracts,id',
            'address' => 'nullable|string',
            'nationality_id_fk' => 'nullable|exists:nationalities,id',
            'dob' => 'nullable|date',
            'gender_id_fk' => 'required|exists:genders,id',
            'age' => 'required|integer',
            'age_unit_id_fk' => 'required|exists:age_units,id',
            'passport_no' => 'nullable|string',
            'national_id_no' => 'nullable|integer',
        ]);

        $code = random_int(100000, 999999);

        // SECURITY: Use secure file upload with random filename
        $image = '';
        if ($request->hasFile('image') && !is_string($request->image)) {
            $result = $this->secureUploadImage($request->file('image'), 'users');
            if (!$result['success']) {
                return response()->json(['message' => $result['error']], 422);
            }
            $image = $result['url'];
        }

        if (!$request->email) {
            $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $validatedData['name']));
            $domain = 'example.com';
            $random = substr(md5(strval(rand())), 0, 6);
            $validatedData['email'] = $username . $random . '@' . $domain;
        }

        $user = User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'password' => Hash::make(Str::random(16)),
            'phone_number' => $validatedData['phone_number'] ?? null,
            'image' => $image,
            'email_verified_at' => new DateTime,
            'creator_id' => Auth::user()->id,
            'address' => $validatedData['address'] ?? null,
            'role_id' => 3,
        ]);

        if (!$user) {
            return response()->json(['message' => 'Failed to create user'], 500);
        }

        $patient = Patient::create([
            'code' => $code,
            'title_id_fk' => $validatedData['title_id_fk'] ?? null,
            'image' => $image,
            'lab_card' => '',
            'barcode' => Patient::generateUniqueBarcode(),
            'parent_id' => Auth::user()->role_id === 2 ? null : Auth::user()->creator_id,
            'creator_id' => Auth::user()->id,
            'contract_id_fk' => $validatedData['contract_id_fk'] ?? null,
            'address' => $validatedData['address'] ?? null,
            'nationality_id_fk' => $validatedData['nationality_id_fk'] ?? null,
            'dob' => $validatedData['dob'] ?? null,
            'gender_id_fk' => $validatedData['gender_id_fk'] ?? null,
            'age' => $validatedData['age'] ?? null,
            'age_unit_id_fk' => $validatedData['age_unit_id_fk'] ?? null,
            'passport_no' => $validatedData['passport_no'] ?? null,
            'national_id_no' => $validatedData['national_id_no'] ?? null,
            'user_id' => $user->id,
        ]);
        if (!$patient) {
            return response()->json(['message' => 'Failed to create patient'], 500);
        }
        ActivityLogController::storeActivity('إنشاء مريض', $patient);
        ActivityLogController::storeActivity('إنشاء مستخدم', $user);
        $patient = $patient->load('user');
        $patient = PatientResource::make($patient);

        return response()->json($patient, 201);
    }

    /**
     * Convert string representations of null to actual null values
     * 
     * @param Request $request
     * @param array $fields
     * @return void
     */
    private function convertNullStrings(Request $request, array $fields): void
    {
        $requestData = $request->all();

        foreach ($fields as $field) {
            if (
                isset($requestData[$field]) &&
                in_array($requestData[$field], ['null', 'undefined', '', 'NULL'], true)
            ) {
                $requestData[$field] = null;
            }
        }

        $request->replace($requestData);
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('patients edit')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Clean up null string values before validation
        $this->convertNullStrings($request, [
            'email',
            'phone_number',
            'contract_id_fk',
            'address',
            'nationality_id_fk',
            'dob',
            'passport_no',
            'national_id_no'
        ]);

        $validatedData = $request->validate([
            'id' => 'required|integer',
            'title_id_fk' => 'required|exists:titles,id',
            'name' => 'required|string',
            'email' => 'nullable|email',
            'phone_number' => ['nullable', 'string', 'regex:/^\+964[0-9]{10}$/'],
            'image' => 'nullable',
            'contract_id_fk' => 'nullable|exists:contracts,id',
            'address' => 'nullable|string',
            'nationality_id_fk' => 'nullable|exists:nationalities,id',
            'dob' => 'nullable|date',
            'gender_id_fk' => 'nullable|exists:genders,id',
            'age' => 'nullable|integer',
            'age_unit_id_fk' => 'nullable|exists:age_units,id',
            'passport_no' => 'nullable|string',
            'national_id_no' => 'nullable|integer',
        ]);

        $patient = Patient::find($request->id);

        if (!$patient) {
            return response()->json(['message' => 'Patient not found'], 404);
        }

        // SECURITY: Authorization check - verify user has access to update this patient
        $authUser = Auth::user();
        if ($authUser->role_id !== 1) { // Not admin
            if ($patient->creator_id !== $authUser->id && $patient->parent_id !== $authUser->id) {
                return response()->json(['message' => 'Unauthorized to update this patient'], 403);
            }
        }

        $user = User::find($patient->user_id);
        $old_patient = clone $patient;
        $old_user = clone $user;


        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // SECURITY: Use secure file upload
        if ($request->hasFile('image') && !is_string($request->image)) {
            // Delete old image
            $this->safeDeleteFile($user->image);

            // Upload new image securely
            $result = $this->secureUploadImage($request->file('image'), 'users');
            if (!$result['success']) {
                return response()->json(['message' => $result['error']], 422);
            }
            $user->image = $result['url'];
            $patient->image = $result['url'];
        }

        $user->name = $validatedData['name'];
        $user->phone_number = $validatedData['phone_number'] ?? $user->phone_number;
        $user->address = $validatedData['address'] ?? $user->address;
        $user->save();

        $patient->title_id_fk = $validatedData['title_id_fk'] ?? $patient->title_id_fk;
        $patient->contract_id_fk = $validatedData['contract_id_fk'] ?? $patient->contract_id_fk;
        $patient->address = $validatedData['address'] ?? $patient->address;
        $patient->nationality_id_fk = $validatedData['nationality_id_fk'] ?? $patient->nationality_id_fk;
        $patient->dob = $validatedData['dob'] ?? $patient->dob;
        $patient->gender_id_fk = $validatedData['gender_id_fk'] ?? $patient->gender_id_fk;
        $patient->age = $validatedData['age'] ?? $patient->age;
        $patient->age_unit_id_fk = $validatedData['age_unit_id_fk'] ?? $patient->age_unit_id_fk;
        $patient->passport_no = $validatedData['passport_no'] ?? $patient->passport_no;
        $patient->national_id_no = $validatedData['national_id_no'] ?? $patient->national_id_no;
        $patient->save();

        $patient = Patient::with(['contract', 'title', 'nationality', 'gender', 'ageUnit', 'user.role'])
            ->findOrFail($request->id);

        $patient_data = PatientResource::make($patient);

        ActivityLogController::updateActivity('تعديل بيانات المريض', $old_patient, $patient);
        ActivityLogController::updateActivity('تعديل بيانات المستخدم', $old_user, $user);

        return response()->json([
            'message' => 'Patient updated successfully',
            'patient' => $patient_data,
        ]);
    }

    public function destroy(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('patients delete')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $patient = Patient::find($request->id);

        if (!$patient) {
            return response()->json(['message' => 'Patient not found'], 404);
        }

        // SECURITY: Authorization check - verify user has access to delete this patient
        $authUser = Auth::user();
        if ($authUser->role_id !== 1) { // Not admin
            if ($patient->creator_id !== $authUser->id && $patient->parent_id !== $authUser->id) {
                return response()->json(['message' => 'Unauthorized to delete this patient'], 403);
            }
        }

        $user = User::find($patient->user_id);
        $patient->delete();
        if ($user) {
            $user->delete();
        }
        ActivityLogController::deleteActivity('حذف مريض', $patient);

        return response()->json(['message' => 'Patient deleted successfully']);
    }
}
