<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'title_id_fk',
        'image',
        'lab_card',
        'contract_id_fk',
        'address',
        'nationality_id_fk',
        'creator_id',
        'parent_id',
        'dob',
        'gender_id_fk',
        'age',
        'age_unit_id_fk',
        'passport_no',
        'national_id_no',
        'user_id',
        'barcode',
        'loyalty_points',
        'loyalty_tier',
        'loyalty_year_points',
        'loyalty_joined_at',
        'referred_by_patient_id',
    ];

    protected $casts = [
        'dob' => 'date',
        'age' => 'integer',
        'loyalty_points' => 'integer',
        'loyalty_year_points' => 'integer',
        'loyalty_joined_at' => 'datetime',
    ];

    public function title()
    {
        return $this->belongsTo(Title::class, 'title_id_fk')->withTrashed();
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id_fk')->withTrashed();
    }

    public function nationality()
    {
        return $this->belongsTo(Nationality::class, 'nationality_id_fk')->withTrashed();
    }

    public function gender()
    {
        return $this->belongsTo(Gender::class, 'gender_id_fk');
    }

    public function ageUnit()
    {
        return $this->belongsTo(AgeUnit::class, 'age_unit_id_fk');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function referredBy()
    {
        return $this->belongsTo(Patient::class, 'referred_by_patient_id');
    }

    public function loyaltyTransactions()
    {
        return $this->hasMany(LoyaltyTransaction::class, 'patient_id_fk');
    }

    public function portalAccessTokens()
    {
        return $this->hasMany(PortalAccessToken::class, 'patient_id_fk');
    }

    public function lab()
    {
        return $this->belongsTo(User::class, 'creator_id')->withTrashed();
    }

    public static function generateUniqueBarcode()
    {
        $timestamp = now()->timestamp;
        $randomNumber = mt_rand(100, 999);
        $uniqueNumber = substr($timestamp.$randomNumber, 0, 12);

        return $uniqueNumber;
    }
}
