<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CollectionTask extends Model
{
    protected $attributes = ['status'=>'requested','version'=>1];
    protected $guarded = ['id'];
    protected $casts = ['snapshot'=>'array','version'=>'integer','sample_count'=>'integer','collected_at'=>'datetime','delivered_at'=>'datetime','cancelled_at'=>'datetime'];
    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function collector() { return $this->belongsTo(User::class,'collector_id')->withTrashed(); }
    public function sourceLab() { return $this->belongsTo(User::class,'source_lab_id')->withTrashed(); }
}
