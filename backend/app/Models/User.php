<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'status',
        'image',
        'creator_id',
        'phone_number',
        'address',
        'signiture',
        'email_verified_at',
        'print_margins',
    ];

    // Add eager loading of permissions
    // protected $with = ['roles', 'permissions'];

    protected $guard_name = 'api';

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id')->withTrashed();
    }

    public function referals()
    {
        return $this->hasOne(Referal::class, 'referral_id_fk');
    }

    public function patient()
    {
        return $this->hasOne(Patient::class, 'user_id');
    }

    public function lab()
    {
        return $this->hasOne(Lab::class, 'user_id_fk');
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class, 'lab_id_fk')->latestOfMany();
    }

    public function labSetting()
    {
        return $this->hasOne(LabSetting::class, 'lab_id_fk');
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'print_margins' => 'array',
        ];
    }
}
