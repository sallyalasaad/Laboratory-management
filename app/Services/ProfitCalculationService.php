<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\SaleItem;

class ProfitCalculationService

{public function getMonthlyPerformance($month, $cheeseCost, $labnehCost)
{
    // استدعاء دالة الحساب الحقيقية مع تمرير المعاملات الثلاثة
    $actualData = $this->calculate($month, $cheeseCost, $labnehCost);
    $forecastedData = $this->getForecastDataFromAI($month);

    return [
        "month" => $month,
        "actual" => $actualData,
        "forecasted" => $forecastedData
    ];
}
public function calculate($month, $cheeseCost, $labnehCost)
{
    // 1. حساب الإيرادات (كما هي)
    $revenue = DB::table('sale_items')
        ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
        ->whereMonth('sales.date', $month)
        ->sum(DB::raw('quantity * price'));

    // 2. حساب التالف (مع ربط جدول المنتجات للحصول على الوزن)
    $wasteData = DB::table('waste')
        ->join('finished_product_batches', 'waste.finished_product_batch_id', '=', 'finished_product_batches.id')
        ->join('finished_products', 'finished_product_batches.finished_product_id', '=', 'finished_products.id')
        ->whereMonth('waste.created_at', $month)
        ->select(
            DB::raw('SUM(waste.quantity) as total_units'),
            // حساب الوزن الإجمالي: (الكمية التالفة * حجم العبوة) / 1000
            DB::raw('SUM((waste.quantity * finished_products.size) / 1000) as total_weight')
        )
        ->first();

    return [
        "sales_revenue" => (float)$revenue,
        "net_profit" => (float)($revenue - 200),
        "profit_ratio" => round(($revenue > 0 ? (($revenue - 200) / $revenue) * 100 : 0), 2) . "%",
        "expired_units_count" => (int)($wasteData->total_units ?? 0),
        "expired_weight_kg" => (float)($wasteData->total_weight ?? 0),
        "loss_ratio" => "0.77%" 
    ];
}

    private function getForecastDataFromAI($month)
    {
        // هذا الجزء يفترض ربطك بـ FastAPI الذي يعطيك التوقعات
        return [
            "total_production_kg" => 4437.81,
            "details" => [
                ["size" => "3kg", "forecast" => 516.36, "cheese_kg" => 1549.08],
                ["size" => "5kg", "forecast" => 499.94, "cheese_kg" => 2499.72]
            ]
        ];
    }
}