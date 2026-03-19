<?php

namespace App\Observers;

use App\Models\ProductionOrder;

class ProductionOrderObserver
{
    /**
     * Handle the ProductionOrder "created" event.
     */

    /**
     * Handle the ProductionOrder "creating" event.
     */
    public function creating(ProductionOrder $order)
    {
        // توليد رقم أمر الإنتاج بشكل تلقائي
        $lastOrder = ProductionOrder::latest('id')->first();
        $nextId = $lastOrder ? $lastOrder->id + 1 : 1;

        // مثال: FP-001، FP-002
        $order->order_number = str_pad($nextId, 3, '0', STR_PAD_LEFT);    }


    /**
     * Handle the ProductionOrder "updated" event.
     */
    public function updated(ProductionOrder $productionOrder): void
    {
        //
    }

    /**
     * Handle the ProductionOrder "deleted" event.
     */
    public function deleted(ProductionOrder $productionOrder): void
    {
        //
    }

    /**
     * Handle the ProductionOrder "restored" event.
     */
    public function restored(ProductionOrder $productionOrder): void
    {
        //
    }

    /**
     * Handle the ProductionOrder "force deleted" event.
     */
    public function forceDeleted(ProductionOrder $productionOrder): void
    {
        //
    }
}
