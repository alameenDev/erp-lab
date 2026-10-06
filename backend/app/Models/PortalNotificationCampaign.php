<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PortalNotificationCampaign extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected $hidden = ['payload_hash', 'request_id'];
}
