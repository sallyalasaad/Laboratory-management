<?php
namespace App\Services;
use App\Models\SaleItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;
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
}private function getForecastDataFromAI($month)
    {
        try {
            $response = Http::timeout(5)->get("http://127.0.0.1:8001/forecast", [
                'target_month' => '2026-' . str_pad($month, 2, '0', STR_PAD_LEFT)
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'forecast' => $data['forecast'] ?? null,
                    'monthly_capacity' => $data['monthly_capacity'] ?? null,
                    'month' => $data['month'] ?? null,
                    'production_kg' => $data['production_kg'] ?? null,
                ];
            }

            Log::error("Forecast API responded with: " . $response->status());
            return ["total_production_kg" => 0, "details" => []];

        } catch (Exception $e) {
            Log::error("Forecast API Connection Exception: " . $e->getMessage());
            return ["total_production_kg" => 0, "details" => []];
        }
    }public function calculate($month, $cheeseCost, $labnehCost)
{
    // 1. استخراج الإيرادات وأوزان المنتجات المباعة
    $salesData = DB::table('sale_items')
        ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
        ->join('finished_product_batches', 'sale_items.finished_product_batch_id', '=', 'finished_product_batches.id')
        ->join('finished_products', 'finished_product_batches.finished_product_id', '=', 'finished_products.id')
        ->whereMonth('sales.date', $month)
        ->select(
            DB::raw('SUM(sale_items.quantity * sale_items.price) as total_revenue'),
            DB::raw("SUM(CASE WHEN finished_products.name LIKE '%جبنة%' THEN 
                (sale_items.quantity * (CASE WHEN finished_products.unit = 'غرام' THEN finished_products.size / 1000 ELSE finished_products.size END)) 
                ELSE 0 END) as cheese_weight_kg"),
            DB::raw("SUM(CASE WHEN finished_products.name LIKE '%لبنة%' THEN 
                (sale_items.quantity * (CASE WHEN finished_products.unit = 'غرام' THEN finished_products.size / 1000 ELSE finished_products.size END)) 
                ELSE 0 END) as labneh_weight_kg")
        )
        ->first();

    $revenue = $salesData->total_revenue ?? 0;

    // حساب تكلفة المنتجات المباعة
    $totalCost = 
        ($salesData->cheese_weight_kg * $cheeseCost) + 
        ($salesData->labneh_weight_kg * $labnehCost);


    // 2. استعلام التالف
    $wasteData = DB::table('finished_product_batches')
        ->whereMonth('expiry_date', $month)
        ->where('expiry_date', '<', now())
        ->where('remaining_quantity', '>', 0)
        ->join('finished_products', 'finished_product_batches.finished_product_id', '=', 'finished_products.id')
        ->select(
            DB::raw('SUM(finished_product_batches.remaining_quantity) as expired_units'),

            DB::raw("SUM(
                finished_product_batches.remaining_quantity *
                (CASE WHEN finished_products.unit = 'غرام'
                    THEN finished_products.size / 1000
                    ELSE finished_products.size
                END)
            ) as expired_weight_kg"),

            DB::raw("SUM(
                finished_product_batches.remaining_quantity *
                (CASE WHEN finished_products.unit = 'غرام'
                    THEN finished_products.size / 1000
                    ELSE finished_products.size
                END) *
                (CASE WHEN finished_products.name LIKE '%جبنة%'
                    THEN $cheeseCost
                    ELSE $labnehCost
                END)
            ) as total_waste_loss")
        )
        ->first();


    $wasteLoss = (float)($wasteData->total_waste_loss ?? 0);
    $expiredWeight = (float)($wasteData->expired_weight_kg ?? 0);


    // 3. الحسابات المالية
    $operatingProfit = $revenue - $totalCost;

    $finalNetResult = $operatingProfit - $wasteLoss;


    // 4. النسب المئوية

    // نسبة الربح من المبيعات
    $operatingProfitRatio = $revenue > 0
        ? round(($operatingProfit / $revenue) * 100, 2)
        : 0;


    // نسبة النتيجة النهائية مقارنة بالتكلفة الكلية
    $totalInvestment = $totalCost + $wasteLoss;

    $finalProfitRatio = $totalInvestment > 0
        ? round(($finalNetResult / $totalInvestment) * 100, 2)
        : 0;



    // 5. الفجوة البيعية من التنبؤ
    $forecastData = $this->getForecastDataFromAI($month);

    $targetProduction = $forecastData['production_kg'] ?? 0;

    $actualSoldWeight =
        ($salesData->cheese_weight_kg ?? 0) +
        ($salesData->labneh_weight_kg ?? 0);

    $remainingToSell = max(0, $targetProduction - $actualSoldWeight);



    return [

        "sales_revenue" => (float)$revenue,

        "total_production_cost" => (float)$totalCost,

        "profit_from_sales" => (float)$operatingProfit,

        "waste_loss" => (float)$wasteLoss,

        "final_net_result" => (float)$finalNetResult,


        "ratios" => [

            "operating_profit_ratio" => $operatingProfitRatio . "%",

            "final_profit_ratio" => $finalProfitRatio . "%"

        ],


        "expired_stats" => [

            "units" => (int)($wasteData->expired_units ?? 0),

            "weight_kg" => $expiredWeight

        ],


        "sales_target_gap" => [

            "remaining_weight_to_sell_kg" => (float)$remainingToSell

        ]
    ];
}}