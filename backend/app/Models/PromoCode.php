<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PromoCode extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'lab_id_fk',
        'code',
        'label',
        'discount_type',
        'discount_value',
        'max_uses',
        'used_count',
        'max_uses_per_patient',
        'min_invoice_amount',
        'expires_at',
        'is_active',
        'batch_label',
        'created_by',
    ];

    protected $casts = [
        'discount_value' => 'float',
        'max_uses' => 'integer',
        'used_count' => 'integer',
        'max_uses_per_patient' => 'integer',
        'min_invoice_amount' => 'integer',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function lab()
    {
        return $this->belongsTo(User::class, 'lab_id_fk');
    }

    public function redemptions()
    {
        return $this->hasMany(PromoCodeRedemption::class, 'promo_code_id_fk');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function hasRemainingUses(): bool
    {
        return $this->max_uses === null || $this->used_count < $this->max_uses;
    }
}
