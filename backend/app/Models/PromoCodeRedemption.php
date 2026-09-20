<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoCodeRedemption extends Model
{
    protected $fillable = [
        'promo_code_id_fk',
        'invoice_id_fk',
        'patient_id_fk',
        'discount_amount',
    ];

    protected $casts = [
        'discount_amount' => 'integer',
    ];

    public function promoCode()
    {
        return $this->belongsTo(PromoCode::class, 'promo_code_id_fk');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id_fk');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id_fk');
    }
}
