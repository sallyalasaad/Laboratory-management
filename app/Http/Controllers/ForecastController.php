<?php

namespace App\Http\Controllers;

use App\Models\ForecastResult;
use App\Models\FinishedProductBatch;
use App\Models\RawMaterial;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;

class ForecastController extends Controller
{
    protected array $sizeToKg = [
        '350g' => 0.35,
        '1kg' => 1,
        '3kg' => 3,
        '5kg' => 5,
    ];

    public function forecast(Request $request)
    {
        $user = Auth::user();

        if (!$user || !in_array($user->role, ['super_admin', 'accountant'])) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $month = $request->input('month');

        if (!$month) {
            return response()->json([
                'ok' => false,
                'message' => 'Month is required'
            ], 422);
        }

        try {
            $monthDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid month format. Use YYYY-MM'
            ], 422);
        }
$forecastServiceUrl = env('FORECAST_SERVICE_URL', 'http://127.0.0.1:8001');

$response = Http::get($forecastServiceUrl . '/forecast', [
    'target_month' => $month
]);
        if (!$response->successful()) {
            return response()->json([
                'ok' => false,
                'message' => 'Forecast service error',
                'detail' => $response->body(),
            ], $response->status());
        }

        $forecastPayload = $response->json();

        if (isset($forecastPayload['error'])) {
            return response()->json([
                'ok' => false,
                'message' => $forecastPayload['error']
            ], 422);
        }

        $forecastData = $forecastPayload['forecast'] ?? [];
        $totalForecastKg = $forecastPayload['production_kg'] ?? 0;
        $batches = $forecastPayload['batches'] ?? 0;
        $monthlyCapacity = $forecastPayload['monthly_capacity'] ?? 0;
        $surplus = $forecastPayload['surplus'] ?? 0;
        $schedule = $forecastPayload['schedule'] ?? '';
        $materials = $forecastPayload['materials'] ?? [];

        $stockData = $this->calculateStockBySize();
        $rawMaterialMap = $this->getRawMaterialMap();
        $materials = $this->normalizeMaterialNames($materials, $rawMaterialMap);
        $totalStockKg = array_reduce(array_values($stockData), function ($carry, $item) {
            return $carry + ($item['stock_kg'] ?? 0);
        }, 0.0);
        $totalStockKg = round($totalStockKg, 2);

        $finalData = [];
        $totalFinalKg = 0.0;

        foreach ($forecastData as &$sizeRow) {
            $size = $this->normalizeSize($sizeRow['size'] ?? null);
            $sizeRow['size'] = $size;
            $forecastQty = isset($sizeRow['forecast']) ? (float) $sizeRow['forecast'] : 0;
            $stockQty = $stockData[$size]['stock_quantity'] ?? 0;
            $sizeKg = $this->sizeToKg[$size] ?? 1;
            $finalQty = max(0, $forecastQty - $stockQty);
            $finalKg = round($finalQty * $sizeKg, 2);
            $totalFinalKg += $finalKg;

            $finalData[] = [
                'size' => $size,
                'forecast_quantity' => round($forecastQty, 2),
                'stock_quantity' => round($stockQty, 2),
                'final_quantity' => round($finalQty, 2),
                'size_kg' => $sizeKg,
                'final_kg' => $finalKg,
            ];

            if (!isset($stockData[$size])) {
                $stockData[$size] = [
                    'stock_quantity' => 0.0,
                    'stock_kg' => 0.0,
                ];
            }
        }

        unset($sizeRow);

        $totalFinalKg = round($totalFinalKg, 2);

        ForecastResult::updateOrCreate(
            ['month' => $month],
            [
                'month_date' => $monthDate,
                'forecast_production_kg' => round($totalForecastKg, 2),
                'final_production_kg' => $totalFinalKg,
                'batches' => $batches,
                'monthly_capacity' => round($monthlyCapacity, 2),
                'surplus' => round($surplus, 2),
                'schedule' => $schedule,
                'forecast_data' => $forecastData,
                'stock_data' => $stockData,
                'final_data' => $finalData,
                'materials' => $materials,
                'created_by' => $user->id,
            ]
        );

        return response()->json([
            'ok' => true,
            'message' => 'Forecast calculated and saved successfully',
            'data' => [
                'forecast' => $forecastData,
                'stock_data' => $stockData,
                'final_data' => $finalData,
                'month' => $month,
                'forecast_production_kg' => round($totalForecastKg, 2),
                'stock_kg' => $totalStockKg,
                'final_production_kg' => $totalFinalKg,
                'batches' => $batches,
                'monthly_capacity' => round($monthlyCapacity, 2),
                'surplus' => round($surplus, 2),
                'schedule' => $schedule,
                'materials' => $materials,
            ],
        ], 200);
    }

    public function showSavedForecast($month)
    {
        $user = Auth::user();

        if (!$user || !in_array($user->role, ['super_admin', 'accountant'])) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        try {
            Carbon::createFromFormat('Y-m', $month);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid month format. Use YYYY-MM'
            ], 422);
        }

        $forecastResult = ForecastResult::where('month', $month)->first();

        if (!$forecastResult) {
            return response()->json([
                'ok' => false,
                'message' => 'No saved forecast found for the requested month'
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'data' => $forecastResult,
        ]);
    }

    private function calculateStockBySize(): array
    {
        $stockData = [];

        $batches = FinishedProductBatch::with('finishedProduct')
            ->where('remaining_quantity', '>', 0)
            ->get();

        foreach ($batches as $batch) {
            $product = $batch->finishedProduct;

            if (!$product) {
                continue;
            }

            $sizeLabel = $product->unit === 'غرام'
                ? $product->size . 'g'
                : $product->size . 'kg';

            $sizeLabel = $this->normalizeSize($sizeLabel);

            $stockData[$sizeLabel]['stock_quantity'] = ($stockData[$sizeLabel]['stock_quantity'] ?? 0) + $batch->remaining_quantity;
            $stockData[$sizeLabel]['stock_kg'] = round(
                ($stockData[$sizeLabel]['stock_kg'] ?? 0) + ($batch->remaining_quantity * ($this->sizeToKg[$sizeLabel] ?? 1)),
                2
            );
        }

        foreach ($stockData as $size => $data) {
            $stockData[$size]['stock_quantity'] = round($data['stock_quantity'], 2);
            $stockData[$size]['stock_kg'] = round($data['stock_kg'], 2);
        }

        return $stockData;
    }

    private function normalizeSize(?string $size): ?string
    {
        if (!$size) {
            return $size;
        }

        $size = trim($size);
        $size = str_replace(' ', '', $size);

        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)(kg|g)$/i', $size, $matches)) {
            $number = (float) $matches[1];
            $unit = strtolower($matches[2]);

            if (floor($number) == $number) {
                $value = (string) (int) $number;
            } else {
                $value = rtrim(rtrim(number_format($number, 10, '.', ''), '0'), '.');
            }

            if ($value === '') {
                $value = '0';
            }

            return $value . $unit;
        }

        return $size;
    }

    private function getRawMaterialMap(): array
    {
        $rawMaterialNames = RawMaterial::pluck('name')->toArray();
        $map = [];

        foreach ($rawMaterialNames as $name) {
            $map[$this->normalizeMaterialKey($name)] = $name;
        }

        return $map;
    }

    private function normalizeMaterialNames(array $materials, array $rawMaterialMap): array
    {
        $normalized = [];

        foreach ($materials as $name => $quantity) {
            $normalizedKey = $this->normalizeMaterialKey($name);
            $matchedName = $rawMaterialMap[$normalizedKey] ?? null;

            if (!$matchedName) {
                foreach ($rawMaterialMap as $rawKey => $rawName) {
                    if (str_contains($normalizedKey, $rawKey) || str_contains($rawKey, $normalizedKey)) {
                        $matchedName = $rawName;
                        break;
                    }
                }
            }

            if (!$matchedName) {
                $synonyms = [
                    'kashkavalmilk' => 'Qashqwan',
                    'milk' => 'Milk',
                    'butter' => 'Butter',
                    'salt' => 'Salt',
                    'flavor' => 'Flavor',
                    'preservatives' => 'Preservatives',
                    'acid' => 'Citric Acid',
                    'water' => 'Water',
                ];

                foreach ($synonyms as $key => $rawName) {
                    if (str_contains($normalizedKey, $key)) {
                        $matchedName = $rawName;
                        break;
                    }
                }
            }

            $outputName = $matchedName ?? $name;
            $normalized[$outputName] = $quantity;
        }

        return $normalized;
    }

    private function normalizeMaterialKey(string $name): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', $name));
    }
}
