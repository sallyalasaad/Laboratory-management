<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name','email','phone','password','role','is_verified','contract_start_date',
        'contract_end_date','otp','otp_created_at'
    ];

    protected $hidden = [
        'password','remember_token'
    ];
    protected $casts = [
        'is_verified' => 'boolean'
    ];

}
