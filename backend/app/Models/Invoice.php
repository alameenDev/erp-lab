<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

    protected $table = 'invoices';

    protected $fillable = [
        'patient_id_fk',
        'from_lab_id_fk',
        'sample_collector_id_fk',
        'contract_id_fk',
        'referral_id_fk',
        'registration_date',
        'result_date',
        'show_result_date',
        'show_patient_card_id',
        'show_patient_pic',
        'sub_total',
        'discount',
        'discount_type_id_fk',
        'total',
        'paid',
        'sent_to_patient',
        'notes',
        'barcode',
        'qr_code',
        'lab_id_fk',
        'result_doc',
        'is_printed',
        'is_signed',
        'signed_by_id_fk',
        'is_done',
        'public_with_background',
        'attachments',
        'pdf_qr_code',
        'tests_comment',
        'cultures_comment',
        'packages_comment',
    ];

    protected $casts = [
        'attachments' => 'json',
        'tests_comment' => 'json',
        'cultures_comment' => 'json',
        'packages_comment' => 'json',
        'is_done' => 'boolean',
        'public_with_background' => 'boolean',
        'is_printed' => 'boolean',
        'is_signed' => 'boolean',
        'sent_to_patient' => 'boolean',
        'show_result_date' => 'boolean',
        'show_patient_card_id' => 'boolean',
        'show_patient_pic' => 'boolean',
        'registration_date' => 'date',
        'result_date' => 'date',
    ];

    /**
     * Relationship to the patient
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id_fk')->withTrashed();
    }

    /**
     * Relationship to the lab (from_lab)
     */
    public function fromLab()
    {
        return $this->belongsTo(User::class, 'from_lab_id_fk')->withTrashed();
    }

    public function lab()
    {
        return $this->belongsTo(User::class, 'lab_id_fk', 'id')->withTrashed();
    }

    public function signedBy()
    {
        return $this->belongsTo(User::class, 'signed_by_id_fk')->withTrashed();
    }

    /**
     * Relationship to the user (sample collector)
     */
    public function sampleCollector()
    {
        return $this->belongsTo(User::class, 'sample_collector_id_fk')->withTrashed();
    }

    /**
     * Relationship to the contract
     */
    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id_fk')->withTrashed();
    }

    /**
     * Relationship to the referral user
     */
    public function referral()
    {
        return $this->belongsTo(User::class, 'referral_id_fk')->withTrashed();
    }

    /**
     * Relationship to discount type
     */
    public function discountType()
    {
        return $this->belongsTo(DiscountType::class, 'discount_type_id_fk');
    }

    /**
     * Relationship to invoice paid details
     */
    public function paidDetails()
    {
        return $this->hasMany(InvoicePaidDetail::class, 'invoice_id_fk');
    }

    /**
     * Relationship to invoice test relationships
     */
    public function invoiceTestRels()
    {
        return $this->hasMany(InvoiceTestRel::class, 'invoice_id_fk');
    }

    public static function generateUniqueBarcode()
    {
        $timestamp = now()->timestamp;
        $randomNumber = mt_rand(100, 999);
        $uniqueNumber = substr($timestamp.$randomNumber, 0, 12);

        return $uniqueNumber;
    }
}
