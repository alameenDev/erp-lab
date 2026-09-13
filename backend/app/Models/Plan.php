<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'name_ar',
        'monthly_price',
        'yearly_price',
        'currency',
        'max_users',
        'max_invoices_per_month',
        'features',
        'is_popular',
        'is_custom',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'monthly_price' => 'integer',
        'yearly_price' => 'integer',
        'max_users' => 'integer',
        'max_invoices_per_month' => 'integer',
        'features' => 'array',
        'is_popular' => 'boolean',
        'is_custom' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'plan_id_fk');
    }
}
