<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'name',
        'payment_percent',
        'maximum_payment_per_invoice',
        'credit_limit',
        'price_limit',
        'discount_percentage',
        'address',
        'phone_number',
        'email',
        'password',
        'lab_id_fk',
    ];

    protected $casts = [
        'password' => 'hashed',
    ];

    // Assuming that password is encrypted
    protected $hidden = [
        'password',
    ];

    public function patients()
    {
        return $this->hasMany(Patient::class, 'contract_id_fk');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'contract_id_fk');
    }

    public function lab()
    {
        return $this->belongsTo(User::class, 'lab_id_fk');
    }
}
