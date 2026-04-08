<?php
namespace App\DAO;

use App\Models\Note;
use App\Models\ProductionOrder;

class ProductionOrderDAO
{
    public function create(array $data)
    {
        return ProductionOrder::create($data);
    }

    public function findById($id)
    {
        return ProductionOrder::find($id);
    }

    public function updateStatus(ProductionOrder $order, $status)
    {
        $order->status = $status;
        $order->save();
        return $order;
    }

    public function listAll()
    {
        return ProductionOrder::with('stages')->get();
    }
    public function getActiveOrders()
    {
        return ProductionOrder::where('status','in_progress')
            ->with('stages')
            ->get();
    }
    public function getCurrentOrders()
    {
        return ProductionOrder::with(['product', 'stages', 'notes'])
            ->whereIn('status', ['pending','accepted','materials_received','in_progress','paused']) // exclude rejected/completed
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getOrdersHistory()
    {
        return ProductionOrder::with(['product'])
            ->whereIn('status', [
                'pending','accepted','rejected','materials_received','in_progress','paused','completed'
            ])
            ->orderBy('created_at','desc')
            ->get();
    }public function getIncomingOrders()
{
    return ProductionOrder::with(['product'])
        ->where('status', 'pending') // فقط الطلبات الجديدة التي لم تُعالج بعد
        ->orderBy('created_at', 'desc')
        ->get();
}


    public function getProductionNotes($orderId)
    {
        $notes = Note::with('fromUser')
            ->where('production_order_id', $orderId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($notes);
    }


    public function markAsRead($id)
    {
        $note = Note::findOrFail($id);
        $note->is_read = true;
        $note->save();

        return response()->json(['message' => 'تم القراءة']);
    }
    public function getUnreadCount($orderId)
    {
        $count = Note::where('production_order_id', $orderId)
            ->where('is_read', false)
            ->count();

        return response()->json(['count' => $count]);
    }

}
