<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PortalNotification extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected $casts = ['read_at' => 'datetime', 'expires_at' => 'datetime'];
    public function deliveries() { return $this->hasMany(PortalPushDelivery::class, 'notification_id'); }
    public function patient() { return $this->belongsTo(Patient::class, 'patient_id')->withTrashed(); }
    public function invoice() { return $this->belongsTo(Invoice::class, 'invoice_id')->withTrashed(); }
}
