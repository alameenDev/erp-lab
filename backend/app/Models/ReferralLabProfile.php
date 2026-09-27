<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ReferralLabProfile extends Model
{
    protected $fillable = [
        'user_id_fk',
        'display_name',
        'logo',
        'phone',
        'address',
    ];

    public function getLogoAttribute(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return config('app.url').Storage::url($value);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id_fk');
    }
}
