<?php

namespace App\Http\Controllers;

use App\Services\{PatientMobileAccess, PatientMobileRead};
use Illuminate\Http\Request;

class PatientMobileController extends Controller
{
    public function __construct(private PatientMobileAccess $access, private PatientMobileRead $read) {}

    private function json(array $data, int $status = 200)
    {
        return response()->json($data, $status)->header('Cache-Control', 'private, no-store')
            ->header('Pragma', 'no-cache')->header('Referrer-Policy', 'no-referrer');
    }

    public function availability(string $token)
    {
        [, , , $phone] = $this->access->portal($token);
        return $this->json(['available'=>true, 'has_phone'=>(bool)$phone,
            'phone_hint'=>$phone ? '••••'.substr($phone, -4) : null]);
    }

    public function issue(string $token)
    {
        return $this->json($this->access->issue($token), 201);
    }

    public function revokePortal(string $token)
    {
        $this->access->revokePortal($token);
        return $this->json(['message'=>'تم إلغاء ربط أجهزة التطبيق لهذا الرابط.']);
    }

    public function exchange(Request $request)
    {
        $this->access->enabled();
        $input = $request->validate(['phone'=>'required|string|max:40', 'code'=>'required|string|max:40']);
        return $this->json($this->access->exchange($input['phone'], $input['code']), 201);
    }

    public function overview(Request $request)
    {
        [, $patient, $lab, $user] = $this->access->session($request);
        return $this->json(['patient'=>$this->read->patient($patient, $user), 'lab'=>$this->read->lab($lab),
            'loyalty'=>$this->read->loyalty($patient, $lab), 'reports'=>$this->read->reports($patient, $lab, 1)]);
    }

    public function reports(Request $request)
    {
        [, $patient, $lab] = $this->access->session($request);
        $page = $request->validate(['page'=>'sometimes|integer|min:1|max:10000'])['page'] ?? 1;
        return $this->json($this->read->reports($patient, $lab, (int)$page));
    }

    public function report(Request $request, int $invoice)
    {
        [, $patient, $lab] = $this->access->session($request);
        return $this->json($this->read->report($patient, $lab, $invoice));
    }

    public function ledger(Request $request)
    {
        [, $patient, $lab] = $this->access->session($request);
        $page = $request->validate(['page'=>'sometimes|integer|min:1|max:10000'])['page'] ?? 1;
        return $this->json($this->read->ledger($patient, $lab, (int)$page));
    }

    public function logout(Request $request)
    {
        [$session] = $this->access->session($request);
        $this->access->db()->table('mobile_patient_sessions')->where('id', $session->id)->update(['revoked_at'=>now()]);
        return $this->json(['message'=>'تم تسجيل الخروج.']);
    }
}
