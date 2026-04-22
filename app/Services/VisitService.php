<?php

namespace App\Services;

class VisitService
{
    public function markVisited($task, $storeId)
    {
        $storePivot = $task->stores()
            ->where('store_id', $storeId)
            ->first();

        if ($storePivot && !$storePivot->pivot->visited) {
            $task->stores()->updateExistingPivot($storeId, [
                'visited' => true,
                'visited_at' => now(),
            ]);
        }
    }
}
