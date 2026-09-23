<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Doctor extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'lab_id_fk',
        'name',
        'specialty',
        'description',
        'photo',
        'phone',
        'whatsapp',
        'links',
        'bookable',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'links' => 'array',
        'bookable' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function getPhotoAttribute(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return config('app.url').Storage::url($value);
    }

    public function lab()
    {
        return $this->belongsTo(User::class, 'lab_id_fk');
    }

    public function bookingRequests()
    {
        return $this->hasMany(DoctorBookingRequest::class, 'doctor_id_fk');
    }
}
