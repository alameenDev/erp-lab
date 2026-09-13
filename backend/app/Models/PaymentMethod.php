<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use SoftDeletes;
    protected $table = 'payment_methods';

    protected $fillable = ['name', 'lab_id_fk'];

    /**
     * A payment method can be used in multiple invoice paid details.
     */
    public function invoicePaidDetails()
    {
        return $this->hasMany(InvoicePaidDetail::class, 'payment_method_id_fk');
    }

    public function lab()
    {
        return $this->belongsTo(User::class, 'lab_id_fk')->withTrashed();
    }
}
