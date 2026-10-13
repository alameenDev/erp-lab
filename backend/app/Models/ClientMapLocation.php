<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class ClientMapLocation extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = [
        'payload_hash',
        'request_id',
        'logo_path',
        'created_by',
        'deleted_at',
    ];

    protected $appends = ['logo_url'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'enabled' => 'boolean',
            'sort_order' => 'integer',
            'version' => 'integer',
        ];
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }
}
