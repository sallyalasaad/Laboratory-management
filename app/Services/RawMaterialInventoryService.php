<?php

namespace App\Services;

use App\Models\RawMaterialBatch;
use Illuminate\Support\Facades\DB;

class RawMaterialInventoryService
{
    /**
     * Allocate quantity for a given raw material using FIFO (oldest received first).
     * Returns array of allocations: [ ['batch_id' => id, 'quantity' => q], ... ]
     */
    public function allocateFifo(int $rawMaterialId, float $quantityNeeded): array
    {
        $allocations = [];

        DB::transaction(function () use (&$allocations, $rawMaterialId, $quantityNeeded) {
            $remaining = $quantityNeeded;

            $batches = RawMaterialBatch::where('raw_material_id', $rawMaterialId)
                ->where('remaining_quantity', '>', 0)
                ->orderBy('received_at', 'asc')
                ->lockForUpdate()
                ->get();

            foreach ($batches as $batch) {
                if ($remaining <= 0) break;

                $take = min($batch->remaining_quantity, $remaining);

                // atomically decrement remaining_quantity to avoid race conditions
                RawMaterialBatch::where('id', $batch->id)->decrement('remaining_quantity', $take);

                // reload batch to get current remaining (not strictly necessary for allocation result)
                $batch->refresh();

                $allocations[] = [
                    'batch_id' => $batch->id,
                    'quantity' => $take,
                ];

                $remaining -= $take;
            }

            if ($remaining > 0) {
                // Not enough stock: throw to roll back
                throw new \Exception('Not enough stock for raw material id ' . $rawMaterialId);
            }
        });

        return $allocations;
    }
}
