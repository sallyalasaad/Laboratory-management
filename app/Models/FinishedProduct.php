<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinishedProduct extends Model
{
    use HasFactory;

    protected $fillable = ['name','size','unit','description'];

    public function batches()
    {
        return $this->hasMany(FinishedProductBatch::class);
    }


public function getAvailableStockAttribute()
{
    // يحسب مجموع الكميات المتبقية للدفوعات التي تم استلامها فقط
    return $this->batches()
        ->where('status', 'received')
        ->sum('remaining_quantity');
}

}
