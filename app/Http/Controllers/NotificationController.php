<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;
class NotificationController extends Controller
{
    /**
     * Get logged user notifications
     */
 public function index(Request $request)
{
    $user = Auth::user();

    if ($user->hasRole('admin')) {

        $notifications = DB::table('notifications')
            ->leftJoin('finished_product_batches',
                'notifications.batch_id', '=', 'finished_product_batches.id'
            )
            ->leftJoin('finished_products',
                'finished_product_batches.finished_product_id', '=', 'finished_products.id'
            )
            ->select(
                'notifications.*',
                'finished_product_batches.expiry_date',
                'finished_product_batches.remaining_quantity',
                'finished_products.name as product_name'
            )
            ->orderByRaw("CASE WHEN notifications.status = 'sent' THEN 0 ELSE 1 END")
            ->orderBy('notifications.created_at', 'desc')
            ->get();

    } elseif ($user->hasRole('product_storekeeper')) {

        $notifications = DB::table('notifications')
            ->where('notifications.user_id', $user->id)
            ->leftJoin('finished_product_batches',
                'notifications.batch_id', '=', 'finished_product_batches.id'
            )
            ->leftJoin('finished_products',
                'finished_product_batches.finished_product_id', '=', 'finished_products.id'
            )
            ->select(
                'notifications.*',
                'finished_product_batches.expiry_date',
                'finished_product_batches.remaining_quantity',
                'finished_products.name as product_name'
            )
            ->orderByRaw("CASE WHEN notifications.status = 'sent' THEN 0 ELSE 1 END")
            ->orderBy('notifications.created_at', 'desc')
            ->get();

    } else {

        return response()->json([
            'message' => 'Unauthorized'
        ], 403);

    }


    return response()->json([
        'success' => true,
        'data' => $notifications
    ]);
}
    /**
     * Mark notification as read
     */
    public function markAsRead($id)
    {
        $user = Auth::user();

        $notification = DB::table('notifications')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        }


        DB::table('notifications')
            ->where('id', $id)
            ->update([
                'status' => 'read',
                'updated_at' => now()
            ]);


        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    }

    public function unreadCount()
{
    $count = DB::table('notifications')
        ->where('user_id', Auth::id())
        ->where('status', 'sent')
        ->count();

    return response()->json([
        'count' => $count
    ]);
}
}
