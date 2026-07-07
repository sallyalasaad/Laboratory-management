<?php

namespace App\DAO;

use Illuminate\Support\Facades\DB;

class SalesReportDAO
{public function getDailySales($driverId, $date)
{
    return DB::table('sale_items')
        ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
        ->join('stores', 'stores.id', '=', 'sales.store_id')
        ->join('car_stock_items', 'car_stock_items.id', '=', 'sale_items.car_stock_item_id')
        ->join('finished_products', 'finished_products.id', '=', 'car_stock_items.finished_product_id')

        ->where('sales.user_id', $driverId)
        ->whereDate('sales.date', $date)
        ->where('sales.status', 'confirmed')

        ->select(
            'finished_products.name as product_name',
            'finished_products.size',
            'stores.name as store_name',
            DB::raw('SUM(sale_items.quantity) as total_quantity'),
            DB::raw('SUM(sale_items.quantity * sale_items.price) as total_price')
        )

        ->groupBy(
            'finished_products.id',
            'finished_products.name',
            'finished_products.size',
            'stores.id',
            'stores.name'
        )
        ->get();
}
    public function getMonthlySales($driverId, $month, $year)
    {
        return DB::table('sales')
            ->where('user_id', $driverId)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->where('status', 'confirmed')

            ->select(
                DB::raw('COUNT(*) as invoices_count'),
                DB::raw('SUM(total_amount) as total_sales')
            )
            ->first();
    }
// داخل App\DAO\SalesReportDAO.php

public function getAllDriversMonthlySales($month, $year)
{
    return DB::table('sales')
        ->join('users', 'users.id', '=', 'sales.user_id')
        ->whereMonth('sales.date', $month)
        ->whereYear('sales.date', $year)
        ->where('sales.status', 'confirmed')
        ->select(
            'users.id as driver_id',
            'users.name as driver_name',
            DB::raw('COUNT(sales.id) as invoices_count'),
            DB::raw('SUM(sales.total_amount) as total_sales')
        )
        ->groupBy('users.id', 'users.name')
        ->get();
}







}











