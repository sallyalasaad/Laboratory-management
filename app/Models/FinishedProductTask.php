<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinishedProductTask extends Model
{
    use HasFactory;
        protected $fillable = [
        //'admin_id',
        'user_id',
        'driver_id',
        'route',
        'status',
        'details',
        'sent_at',
    ];
    protected $casts = [
    'details' => 'array',
];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
