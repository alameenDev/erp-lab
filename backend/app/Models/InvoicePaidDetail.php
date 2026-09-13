<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoicePaidDetail extends Model
{
    protected $table = 'invoice_paid_details';

    protected $fillable = [
        'invoice_id_fk',
        'payment_method_id_fk',
        'contract_id_fk',
        'amount',
        'lab_id_fk',
    ];

    /**
     * Relationship to the Invoice model
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id_fk');
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id_fk');
    }

    /**
     * Relationship to the PaymentMethod model
     */
    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id_fk');
    }
}
