<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PortalAccessToken extends Model
{
    protected $fillable = [
        'patient_id_fk',
        'token',
        'otp_code',
        'otp_expires_at',
        'verified_at',
        'expires_at',
        'last_accessed_at',
    ];

    protected $casts = [
        'otp_expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_accessed_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id_fk');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isOtpVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function setOtp(string $code): void
    {
        $this->otp_code = Hash::make($code);
        $this->otp_expires_at = now()->addMinutes(5);
        $this->verified_at = null;
        $this->save();
    }

    public function checkOtp(string $code): bool
    {
        if (! $this->otp_code || ! $this->otp_expires_at || $this->otp_expires_at->isPast()) {
            return false;
        }

        return Hash::check($code, $this->otp_code);
    }

    public static function generatePlainToken(): string
    {
        return Str::random(48);
    }
}
