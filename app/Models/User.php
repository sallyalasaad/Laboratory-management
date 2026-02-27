<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $guard_name = 'sanctum'; // مهم جداً للـ Spatie مع Sanctum
    public function getRoleAttribute()
    {
        return $this->getRoleNames()->first();
    }
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'is_verified',
        'contract_start_date',
        'contract_end_date',
        'otp',
        'otp_created_at'
    ];
    protected $appends = ['role'];

    protected $hidden = [
        'password',
        'remember_token',
        'otp',
        'otp_created_at'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'contract_start_date' => 'datetime',
        'contract_end_date' => 'datetime',
        'is_verified' => 'boolean',
    ];

    public function productionOrders()
    {
        return $this->hasMany(ProductionOrder::class);
    }

    public function distributionTasks()
    {
        return $this->hasMany(DistributionTask::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
}
