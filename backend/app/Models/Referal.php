<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Referal extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'referral_id_fk',
        'lab_id_fk',
        'commission',
        'price_list_id_fk',
    ];

    // table
    protected $table = 'rel_labs_referals';

    public function user()
    {
        return $this->belongsTo(User::class, 'referral_id_fk')->withTrashed();
    }

    public function lab()
    {
        return $this->belongsTo(User::class, 'lab_id_fk');
    }

    public function priceList()
    {
        return $this->belongsTo(PriceList::class, 'price_list_id_fk');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'referral_id_fk', 'referral_id_fk');
    }
}
