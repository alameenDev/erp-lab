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

    public static function bridgeProfiles(): array
    {
        return [
            ['id' => 'dxh500', 'name' => 'Beckman Coulter DxH 500', 'protocol' => 'ASTM LIS2-A2', 'port' => 5001],
            ['id' => 'bm850', 'name' => 'Boule BM850 (Izmir)', 'protocol' => 'HL7 2.7 / MLLP · Barcode OBR-4', 'port' => 5600],
            ['id' => 'np21h', 'name' => 'Nipigon NP-21H', 'protocol' => 'HL7 2.3.1 / MLLP', 'port' => 5600],
        ];
    }

    /** Existing devices retain their DxH configuration until explicitly edited. */
    public function bridgeSettings(): array
    {
        $config = $this->connection_config ?? [];
        return [
            'adapter' => $config['bridge_adapter'] ?? 'dxh500',
            'cbc_interface_code' => (string) ($config['cbc_interface_code'] ?? '12345678'),
            'automatic_invoice_apply' => (bool) ($config['automatic_invoice_apply'] ?? true),
        ];
    }
}
