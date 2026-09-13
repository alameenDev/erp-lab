<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingTestRel extends Model
{
    protected $table = 'booking_test_rel';

    public $timestamps = false;

    protected $fillable = [
        'booking_id_fk',
        'test_id_fk',
        'culture_id_fk',
        'package_id_fk',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id_fk');
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class, 'test_id_fk');
    }

    public function culture(): BelongsTo
    {
        return $this->belongsTo(Culture::class, 'culture_id_fk');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id_fk');
    }
}
