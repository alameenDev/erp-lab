<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'lab_id_fk',
        'plan_id_fk',
        'plan_name',
        'status',
        'start_date',
        'end_date',
        'max_users',
        'max_invoices_per_month',
        'price',
        'currency',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'price' => 'integer',
            'max_users' => 'integer',
            'max_invoices_per_month' => 'integer',
        ];
    }

    public function lab()
    {
        return $this->belongsTo(User::class, 'lab_id_fk')->withTrashed();
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id_fk');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('end_date', '>=', now()->toDateString());
    }
}
