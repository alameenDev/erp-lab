<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CollectionSubmission extends Model
{
    protected $attributes = ['status'=>'pending'];
    protected $guarded = ['id'];
    protected $hidden = ['image_path','request_hash'];
    protected $casts = ['payload'=>'array','reviewed_at'=>'datetime'];
    public function collector() { return $this->belongsTo(User::class,'collector_id')->withTrashed(); }
}
