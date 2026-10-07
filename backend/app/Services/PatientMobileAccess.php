<?php

namespace App\Services;

use App\Models\{Patient, PortalAccessToken, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, RateLimiter};
use Carbon\Carbon;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class PatientMobileAccess
{
    public const INVALID = 'رقم الهاتف أو رمز الربط غير صحيح، أو انتهت صلاحية الرمز.';

    public function enabled(): void
    {
        abort_unless(config('patient_mobile.enabled'), 404);
    }

    public static function digits(string $value): string
    {
        return strtr($value, ['٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']);
    }

    public static function phone(string $value): ?string
    {
        $value = preg_replace('/[\s()\-]/u', '', self::digits($value));
        return preg_match('/^(?:0|964|\+964|00964)(7[3-9]\d{8})$/D', $value, $m) ? '964'.$m[1] : null;
    }

    public function digest(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    public function db()
    {
        $name = (string) config('database.connections.patient_mobile.database');
        $erp = (string) config('database.connections.'.config('patient_mobile.erp_connection', 'mysql').'.database');
        abort_if($name === '' || $name === $erp, 503, 'خدمة الربط غير مهيأة بعد.');
        return DB::connection('patient_mobile');
    }

    /** Reads existing authorization, without touching OTP, access timestamps or loyalty. */
    public function portal(string $token): array
    {
        $this->enabled();
        [$access, $patient, $lab] = app(PatientPortalAccess::class)->resolve($token);
        abort_unless(in_array((int) $lab->id, config('patient_mobile.lab_ids', []), true), 404);
        $user = User::find($patient->user_id); // Do not inherit withTrashed().
        abort_unless($user && !$lab->trashed(), 404);
        $phone = self::phone((string) $user->phone_number);
        return [$access, $patient, $lab, $phone, $user];
    }

    private function limit(string $key, int $max, int $seconds): void
    {
        abort_if(RateLimiter::tooManyAttempts($key, $max), 429, 'محاولات كثيرة. يرجى المحاولة لاحقاً.');
        RateLimiter::hit($key, $seconds);
    }

    public function issue(string $token): array
    {
        [$access, $patient, $lab, $phone] = $this->portal($token);
        abort_unless($phone, 422, 'اطلب من المختبر تسجيل رقم هاتف عراقي صحيح لملفك.');
        $this->limit('mobile-issue:'.$access->id, 3, 600);
        $code = (string) random_int(100000000000, 999999999999);
        $expires = now()->addMinutes((int) config('patient_mobile.code_minutes', 10));
        if ($access->expires_at && $access->expires_at->lt($expires)) $expires = $access->expires_at->copy();
        $this->db()->transaction(function () use ($access, $patient, $lab, $phone, $code, $expires) {
            // Only the dedicated pairing store is writable here.
            $this->db()->table('mobile_pairing_codes')->where('portal_id', $access->id)
                ->whereNull('consumed_at')->update(['consumed_at' => now()]);
            $this->db()->table('mobile_pairing_codes')->insert([
                'portal_id'=>$access->id, 'patient_id'=>$patient->id, 'lab_id'=>$lab->id,
                'phone_digest'=>$this->digest($phone), 'code_digest'=>$this->digest($phone.':'.$code),
                'expires_at'=>$expires, 'created_at'=>now(),
            ]);
        });
        return ['code'=>$code, 'expires_at'=>$expires->toISOString(), 'phone_hint'=>'••••'.substr($phone, -4)];
    }

    public function exchange(string $phoneInput, string $codeInput): array
    {
        $this->enabled();
        $phone = self::phone($phoneInput);
        $code = preg_replace('/\s/u', '', self::digits($codeInput));
        abort_unless($phone && preg_match('/^\d{12}$/D', $code), 422, self::INVALID);
        // Phone-wide budget applies even when an attacker rotates network addresses.
        $this->limit('mobile-exchange:'.$this->digest($phone), 6, 600);
        return $this->db()->transaction(function () use ($phone, $code) {
            $row = $this->db()->table('mobile_pairing_codes')
                ->where('code_digest', $this->digest($phone.':'.$code))->lockForUpdate()->first();
            abort_unless($row && !$row->consumed_at && Carbon::parse($row->expires_at)->isFuture(), 422, self::INVALID);
            try {
                $portal = PortalAccessToken::find($row->portal_id);
                abort_unless($portal, 404);
                [$access, $patient, $lab, $currentPhone] = $this->portal($portal->token);
            } catch (HttpExceptionInterface|\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
                abort(422, self::INVALID);
            }
            abort_unless((int)$row->patient_id === (int)$patient->id && (int)$row->lab_id === (int)$lab->id
                && $currentPhone === $phone && hash_equals($row->phone_digest, $this->digest($phone)), 422, self::INVALID);
            $bearer = bin2hex(random_bytes(32));
            $expires = now()->addDays((int) config('patient_mobile.session_days', 30));
            if ($access->expires_at && $access->expires_at->lt($expires)) $expires = $access->expires_at->copy();
            $this->db()->table('mobile_patient_sessions')->insert([
                'portal_id'=>$access->id, 'patient_id'=>$patient->id, 'lab_id'=>$lab->id,
                'phone_digest'=>$row->phone_digest, 'token_digest'=>hash('sha256', $bearer),
                'expires_at'=>$expires, 'created_at'=>now(),
            ]);
            $this->db()->table('mobile_pairing_codes')->where('id', $row->id)->update(['consumed_at'=>now()]);
            return ['token'=>$bearer, 'expires_at'=>$expires->toISOString(),
                'patient'=>['id'=>$patient->id, 'name'=>$patient->user?->name, 'code'=>$patient->code],
                'lab'=>['id'=>$lab->id, 'name'=>$lab->name]];
        });
    }

    public function session(Request $request): array
    {
        $this->enabled();
        $bearer = $request->bearerToken() ?? '';
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $bearer), 401, 'أعد ربط ملفك من بوابة المريض.');
        $row = $this->db()->table('mobile_patient_sessions')->where('token_digest', hash('sha256', $bearer))->first();
        abort_unless($row && !$row->revoked_at && Carbon::parse($row->expires_at)->isFuture(), 401, 'انتهى الربط. أعد ربط ملفك من البوابة.');
        try {
            $portal = PortalAccessToken::find($row->portal_id);
            abort_unless($portal, 404);
            [$access, $patient, $lab, $phone, $user] = $this->portal($portal->token);
        } catch (HttpExceptionInterface|\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            abort(401, 'أعد ربط ملفك من بوابة المريض.');
        }
        abort_unless($phone && hash_equals($row->phone_digest, $this->digest($phone))
            && (int)$row->patient_id === (int)$patient->id && (int)$row->lab_id === (int)$lab->id, 401);
        return [$row, $patient, $lab, $user];
    }

    public function revokePortal(string $token): void
    {
        [$access] = $this->portal($token);
        $this->db()->transaction(function () use ($access) {
            // Lock/consume challenges first, so an in-flight exchange cannot escape revocation.
            $this->db()->table('mobile_pairing_codes')->where('portal_id', $access->id)
                ->update(['consumed_at'=>now()]);
            $this->db()->table('mobile_patient_sessions')->where('portal_id', $access->id)
                ->whereNull('revoked_at')->update(['revoked_at'=>now()]);
        });
    }
}
