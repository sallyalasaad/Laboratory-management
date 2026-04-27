<?php

namespace App\Services;

use App\DAO\SalesReportDAO;
class SalesReportService
{
    protected $dao;

    public function __construct(SalesReportDAO $dao)
    {
        $this->dao = $dao;
    }
    public function dailySales($driverId, $date)
    {
        $items = $this->dao->getDailySales($driverId, $date);

        $total = $items->sum('total_price');

        return [
            'items' => $items,
            'total_sales' => $total
        ];
    }

    public function monthlySales($driverId, $month, $year = null)
    {
        $year = $year ?? date('Y');

        return $this->dao->getMonthlySales($driverId, $month, $year);
    }
}
