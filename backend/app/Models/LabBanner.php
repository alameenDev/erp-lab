<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class LabBanner extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $hidden = ['payload_hash', 'request_id', 'image_path', 'mobile_image_path', 'created_by', 'deleted_at'];
    protected $appends = ['image_url', 'mobile_image_url'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'sort_order' => 'integer', 'version' => 'integer', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function targets()
    {
        return $this->belongsToMany(User::class, 'lab_banner_targets', 'banner_id', 'lab_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function getMobileImageUrlAttribute(): ?string
    {
        return $this->mobile_image_path ? Storage::disk('public')->url($this->mobile_image_path) : null;
    }
}
