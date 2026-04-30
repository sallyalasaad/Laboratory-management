<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawMaterialTask extends Model
{
    use HasFactory;

    protected $table = 'raw_material_tasks';

    protected $fillable = [
        'admin_id', 'user_id', 'route', 'status', 'scheduled_at', 'sent_at', 'details'
    ];

    protected $casts = [
        'details' => 'array',
        'scheduled_at' => 'datetime:Y-m-d H:i:s',
        'sent_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }


}
