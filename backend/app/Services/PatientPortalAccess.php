<?php

namespace App\Services;

use App\Models\{Patient, PortalAccessToken, User};

class PatientPortalAccess
{
    public function resolve(string $token): array
    {
        $access = PortalAccessToken::where('token', $token)->first();
        abort_if(!$access || $access->isExpired(), 404, 'الرابط غير صالح أو منتهي الصلاحية');
        $patient = Patient::findOrFail($access->patient_id_fk);
        $lab = $this->lab($patient);
        abort_unless($lab, 404);
        $config = app(LoyaltyService::class)->config($lab);
        abort_if(($config['require_otp'] ?? false) && !$access->isOtpVerified(), 403, 'يرجى إكمال التحقق من رقم الهاتف أولاً');
        return [$access, $patient, $lab];
    }

    public function lab(Patient $patient): ?User
    {
        return $patient->lab ? User::find(app(InventoryService::class)->labId($patient->lab)) : null;
    }
}
