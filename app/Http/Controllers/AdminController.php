<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    // إنشاء موظف
    public function createEmployee(Request $request)
    {
        $data = $request->validate([
            'name'=>'required',
            'email'=>'required|email|unique:users',
            'phone'=>'required|unique:users',
            'password'=>'required|min:6',
            'role' => 'required|in:raw_storekeeper,product_storekeeper,accountant,production_employee,driver',
            'contract_start_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date|after_or_equal:contract_start_date',
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['is_verified'] = true;

        $user = User::create($data);

        return response()->json(['message'=>'تم إنشاء الموظف','data'=>$user]);
    }

// عرض الموظفين
    public function employees()
    {
        return User::whereNotIn('role', ['super_admin'])
            ->orderBy('created_at', 'desc')
            ->get();
    }


// تفعيل / تعطيل
    public function toggleVerify($id)
    {
        $authUser = auth()->user();        // المستخدم اللي عامل الطلب
        $targetUser = User::findOrFail($id); // المستخدم المطلوب تفعيله/تعطيله

        // 1️⃣ ممنوع أي شخص يعطل نفسه
        if ($authUser->id === $targetUser->id) {
            return response()->json([
                'message' => 'لا يمكنك تعطيل حسابك'
            ], 403);
        }

        // 2️⃣ إذا كان Admin
        if ($authUser->role === 'admin') {

            // ممنوع يعطّل Admin أو Super Admin
            if (in_array($targetUser->role, ['admin', 'super_admin'])) {
                return response()->json([
                    'message' => 'لا يمكنك تعديل حساب إداري'
                ], 403);
            }
        }

        // 3️⃣ إذا كان Super Admin
        if ($authUser->role === 'super_admin') {

            // ممنوع يعطّل Super Admin ثاني
            if ($targetUser->role === 'super_admin') {
                return response()->json([
                    'message' => 'لا يمكنك تعطيل Super Admin'
                ], 403);
            }
        }

        // 4️⃣ تنفيذ التفعيل / التعطيل
        $targetUser->is_verified = !$targetUser->is_verified;
        $targetUser->save();

        return response()->json([
            'message' => 'تم التعديل بنجاح',
            'data' => $targetUser
        ]);
    }


// حذف
    public function deleteUser($id)
    {
        User::findOrFail($id)->delete();
        return response()->json(['message'=>'تم الحذف']);
    }
    public function updateEmployee(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|required',
            'email' => 'sometimes|required|email|unique:users,email,'.$user->id,
            'phone' => 'sometimes|required|unique:users,phone,'.$user->id,
            'password' => 'nullable|min:6',
            'role' => 'sometimes|required|in:raw_storekeeper,product_storekeeper,accountant,production_employee,driver',
            'contract_start_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date|after_or_equal:contract_start_date',
        ]);

        if(isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json(['message'=>'تم تحديث بيانات الموظف بنجاح','data'=>$user]);
    }

}
