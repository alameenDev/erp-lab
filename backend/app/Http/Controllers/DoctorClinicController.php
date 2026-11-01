<?php

namespace App\Http\Controllers;

use App\Models\DoctorClinicPatient;
use App\Models\DoctorClinicVisit;
use App\Models\DoctorClinicVisitRevision;
use App\Models\Referal;
use App\Models\ReferralLabProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DoctorClinicController extends Controller
{
    private function doctorId(): int
    {
        $user = Auth::user();
        abort_unless($user && (int) $user->role_id === 5
            && $user->referral_portal_only
            && ! ReferralLabProfile::where('user_id_fk', $user->id)->exists()
            && Referal::where('referral_id_fk', $user->id)->exists(), 403);
        return (int) $user->id;
    }

    private function ownPatient(int $id, int $doctorId): DoctorClinicPatient
    {
        return DoctorClinicPatient::where('doctor_user_id_fk', $doctorId)->findOrFail($id);
    }

    private function ownVisit(int $id, int $doctorId): DoctorClinicVisit
    {
        return DoctorClinicVisit::with('patient')->where('doctor_user_id_fk', $doctorId)->findOrFail($id);
    }

    public function patients(Request $request)
    {
        $doctorId = $this->doctorId();
        $data = $request->validate(['search' => 'nullable|string|max:100']);
        $search = trim($data['search'] ?? '');
        $query = DoctorClinicPatient::where('doctor_user_id_fk', $doctorId)->withCount('visits');
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')
                ->orWhere('phone', 'like', '%'.$search.'%'));
        }
        return response()->json($query->orderByDesc('id')->paginate(30));
    }

    public function storePatient(Request $request)
    {
        $doctorId = $this->doctorId();
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'age_value' => 'required|integer|min:0|max:1560',
            'age_unit' => 'required|in:years,months',
            'phone' => ['required','string','max:24','regex:/^\\+?[0-9 0-9\\-()]{7,24}$/'],
            'gender' => 'nullable|in:male,female,other,unknown',
            'notes' => 'nullable|string|max:3000',
        ]);
        if ($validated['age_unit'] === 'years' && $validated['age_value'] > 130) {
            throw ValidationException::withMessages(['age_value' => 'Age in years must not exceed 130.']);
        }
        $patient = DoctorClinicPatient::create([...$validated, 'doctor_user_id_fk' => $doctorId]);
        return response()->json($patient, 201);
    }

    public function showPatient(int $id)
    {
        $doctorId = $this->doctorId();
        $patient = $this->ownPatient($id, $doctorId);
        return response()->json([
            'patient' => $patient,
            'visits' => $patient->visits()->where('doctor_user_id_fk', $doctorId)
                ->orderByDesc('visit_date')->orderByDesc('queue_number')->paginate(20),
        ]);
    }

    public function updatePatient(Request $request, int $id)
    {
        $doctorId = $this->doctorId();
        $patient = $this->ownPatient($id, $doctorId);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'age_value' => 'sometimes|required|integer|min:0|max:1560',
            'age_unit' => 'sometimes|required|in:years,months',
            'phone' => ['sometimes','required','string','max:24','regex:/^\\+?[0-9 0-9\\-()]{7,24}$/'],
            'gender' => 'nullable|in:male,female,other,unknown',
            'notes' => 'nullable|string|max:3000',
        ]);
        if (($data['age_unit'] ?? $patient->age_unit) === 'years' &&
            ($data['age_value'] ?? $patient->age_value) > 130) {
            throw ValidationException::withMessages(['age_value' => 'Age in years must not exceed 130.']);
        }
        $patient->update($data);
        return response()->json($patient->fresh());
    }

    public function visits(Request $request)
    {
        $doctorId = $this->doctorId();
        $data = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'status' => 'nullable|in:waiting,in_consultation,completed,cancelled',
        ]);
        $date = $data['date'] ?? now('Asia/Baghdad')->toDateString();
        $query = DoctorClinicVisit::with('patient')
            ->where('doctor_user_id_fk', $doctorId)->whereDate('visit_date', $date);
        if (isset($data['status'])) $query->where('status', $data['status']);
        return response()->json([
            'date' => $date,
            'visits' => $query->orderBy('queue_number')->get(),
            'stats' => DoctorClinicVisit::where('doctor_user_id_fk', $doctorId)
                ->whereDate('visit_date', $date)
                ->select('status')->selectRaw('COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function storeVisit(Request $request)
    {
        $doctorId = $this->doctorId();
        $data = $request->validate([
            'clinic_patient_id_fk' => 'required|integer',
            'visit_date' => 'required|date_format:Y-m-d',
            'appointment_time' => 'nullable|date_format:H:i',
        ]);
        $patient = $this->ownPatient((int) $data['clinic_patient_id_fk'], $doctorId);
        $visit = DB::transaction(function () use ($doctorId, $patient, $data) {
            // Lock the doctor's user row so concurrent receptionist requests
            // cannot assign the same queue number to two patients on one date.
            User::whereKey($doctorId)->lockForUpdate()->firstOrFail();
            $number = (int) DoctorClinicVisit::where('doctor_user_id_fk', $doctorId)
                ->whereDate('visit_date', $data['visit_date'])->max('queue_number') + 1;
            return DoctorClinicVisit::create([
                'doctor_user_id_fk' => $doctorId,
                'clinic_patient_id_fk' => $patient->id,
                'visit_date' => $data['visit_date'],
                'appointment_time' => $data['appointment_time'] ?? null,
                'queue_number' => $number,
                'age_at_visit' => $patient->age_value,
                'age_unit_at_visit' => $patient->age_unit,
                'status' => 'waiting',
            ]);
        }, 3);
        return response()->json($visit->load('patient'), 201);
    }

    public function showVisit(int $id)
    {
        return response()->json($this->ownVisit($id, $this->doctorId()));
    }

    public function updateVisit(Request $request, int $id)
    {
        $doctorId = $this->doctorId();
        $visit = $this->ownVisit($id, $doctorId);
        $data = $request->validate([
            'status' => 'sometimes|in:waiting,in_consultation,completed,cancelled',
            'chief_complaint' => 'nullable|string|max:4000',
            'symptoms' => 'nullable|array|max:30',
            'symptoms.*' => 'required|string|max:100',
            'symptom_duration' => 'nullable|string|max:255',
            'history_of_present_illness' => 'nullable|string|max:12000',
            'review_of_systems' => 'nullable|string|max:8000',
            'medical_history' => 'nullable|string|max:8000',
            'surgical_history' => 'nullable|string|max:5000',
            'family_history' => 'nullable|string|max:5000',
            'allergies' => 'nullable|string|max:3000',
            'current_medications' => 'nullable|string|max:5000',
            'vitals' => 'nullable|array',
            'vitals.blood_pressure' => 'nullable|string|max:30',
            'vitals.heart_rate' => 'nullable|string|max:30',
            'vitals.temperature_c' => 'nullable|string|max:30',
            'vitals.respiratory_rate' => 'nullable|string|max:30',
            'vitals.spo2' => 'nullable|string|max:30',
            'vitals.weight_kg' => 'nullable|string|max:30',
            'vitals.height_cm' => 'nullable|string|max:30',
            'physical_examination' => 'nullable|string|max:10000',
            'assessment' => 'nullable|string|max:10000',
            'diagnosis_code' => 'nullable|string|max:64',
            'plan' => 'nullable|string|max:10000',
            'lab_requests' => 'nullable|array|max:30',
            'lab_requests.*.name' => 'required|string|max:255',
            'lab_requests.*.notes' => 'nullable|string|max:2000',
            'imaging_requests' => 'nullable|array|max:30',
            'imaging_requests.*.modality' => 'required|in:XRAY,US,CT,MRI,OTHER',
            'imaging_requests.*.body_part' => 'required|string|max:255',
            'imaging_requests.*.notes' => 'nullable|string|max:2000',
            'prescriptions' => 'nullable|array|max:30',
            'prescriptions.*.generic_name' => 'required|string|max:255',
            'prescriptions.*.strength' => 'nullable|string|max:100',
            'prescriptions.*.form' => 'nullable|string|max:100',
            'prescriptions.*.dose' => 'nullable|string|max:100',
            'prescriptions.*.route' => 'nullable|string|max:100',
            'prescriptions.*.frequency' => 'nullable|string|max:100',
            'prescriptions.*.duration' => 'nullable|string|max:100',
            'prescriptions.*.quantity' => 'nullable|string|max:100',
            'prescriptions.*.instructions' => 'nullable|string|max:2000',
            'prescriptions.*.prn_indication' => 'nullable|string|max:255',
            'prescriptions.*.max_daily_dose' => 'nullable|string|max:100',
            'patient_instructions' => 'nullable|string|max:8000',
            'follow_up_date' => 'nullable|date_format:Y-m-d',
        ]);

        return DB::transaction(function () use ($doctorId, $id, $data) {
            $visit = DoctorClinicVisit::where('doctor_user_id_fk', $doctorId)
                ->lockForUpdate()->findOrFail($id);
            $desiredStatus = $data['status'] ?? $visit->status;
            $prescriptions = $data['prescriptions'] ?? $visit->prescriptions ?? [];
            if ($desiredStatus === 'completed') {
                if (trim((string) ($data['assessment'] ?? $visit->assessment)) === '') {
                    throw ValidationException::withMessages(['assessment' => 'Document the clinical assessment before completing the visit.']);
                }
                foreach ($prescriptions as $index => $med) {
                    foreach (['generic_name', 'strength', 'form', 'dose', 'route', 'frequency', 'duration', 'quantity'] as $field) {
                        if (trim((string) ($med[$field] ?? '')) === '') {
                            throw ValidationException::withMessages(["prescriptions.$index.$field" => 'Complete this medication field before finalizing.']);
                        }
                    }
                    if (strtoupper(trim($med['frequency'] ?? '')) === 'PRN'
                        && (empty($med['prn_indication']) || empty($med['max_daily_dose']))) {
                        throw ValidationException::withMessages(["prescriptions.$index.prn_indication" => 'PRN requires indication and maximum daily dose.']);
                    }
                }
            }
            if ($visit->status === 'completed' && ($data['status'] ?? 'completed') !== 'completed') {
                throw ValidationException::withMessages(['status' => 'A completed medical note cannot be reopened by changing its status.']);
            }
            if ($visit->status === 'cancelled' && $desiredStatus !== 'cancelled') {
                throw ValidationException::withMessages(['status' => 'Cancelled visits cannot be reopened. Create a new visit.']);
            }

            $before = $visit->toArray();
            $visit->fill($data);
            if ($desiredStatus === 'completed' && ! $visit->completed_at) $visit->completed_at = now();
            if ($visit->isDirty()) {
                if ($visit->status === 'completed' || $before['status'] === 'completed') {
                    DoctorClinicVisitRevision::create([
                        'visit_id_fk' => $visit->id,
                        'doctor_user_id_fk' => $doctorId,
                        'previous_values' => $before,
                    ]);
                }
                $visit->save();
            }
            return response()->json($visit->load('patient'));
        }, 3);
    }

    public function visitRevisions(int $id)
    {
        $doctorId = $this->doctorId();
        $this->ownVisit($id, $doctorId);
        return response()->json(DoctorClinicVisitRevision::where('visit_id_fk', $id)
            ->where('doctor_user_id_fk', $doctorId)->orderByDesc('id')
            ->get(['id', 'doctor_user_id_fk', 'created_at']));
    }
}
