<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceResult extends Model
{
    protected $table = 'device_results';

    protected $fillable = [
        'device_id_fk',
        'invoice_id_fk',
        'specimen_barcode',
        'raw_message',
        'parsed_results',
        'status',
        'matched_at',
        'applied_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'parsed_results' => 'json',
            'matched_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function device()
    {
        return $this->belongsTo(LabDevice::class, 'device_id_fk');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id_fk')->withTrashed();
    }
}
