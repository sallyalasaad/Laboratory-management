<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    use HasFactory;

    protected $fillable = [
        'raw_material_task_id', 'production_order_id',
        'from_user_id', 'to_user_id', 'message', 'is_read'
    ];

    protected $casts = [
        'is_read' => 'boolean'
    ];

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function task()
    {
        return $this->belongsTo(RawMaterialTask::class, 'raw_material_task_id');
    }
    public function productionOrder()
    {
        return $this->belongsTo(ProductionOrder::class);
    }
}
