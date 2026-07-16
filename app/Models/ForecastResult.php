<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ForecastResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'month',
        'month_date',
        'forecast_production_kg',
        'final_production_kg',
        'batches',
        'monthly_capacity',
        'surplus',
        'schedule',
        'forecast_data',
        'stock_data',
        'final_data',
        'materials',
        'created_by',
    ];

    protected $casts = [
        'month_date' => 'date',
        'forecast_data' => 'array',
        'stock_data' => 'array',
        'final_data' => 'array',
        'materials' => 'array',
    ];
}
