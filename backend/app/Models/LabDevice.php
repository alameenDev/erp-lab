<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabDevice extends Model
{
    use SoftDeletes;

    protected $table = 'lab_devices';

    protected $fillable = [
        'lab_id_fk',
        'name',
        'device_type',
        'serial_number',
        'connection_type',
        'connection_config',
        'api_token',
        'status',
        'last_seen_at',
    ];

    protected $hidden = [
        'api_token',
    ];

    protected function casts(): array
    {
        return [
            'connection_config' => 'json',
            'last_seen_at' => 'datetime',
        ];
    }

    public function lab()
    {
        return $this->belongsTo(User::class, 'lab_id_fk')->withTrashed();
    }

    public function deviceResults()
    {
        return $this->hasMany(DeviceResult::class, 'device_id_fk');
    }

    public static function generateApiToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
