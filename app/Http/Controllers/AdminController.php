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
            'role' => 'required|in:raw_storekeeper,product_storekeeper,accountant,production_employee,driver'
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['is_verified'] = true;

        $user = User::create($data);

        return response()->json(['message'=>'تم إنشاء الموظف','data'=>$user]);
    }

// عرض الموظفين
    public function employees()
    {
        return User::whereNotIn('role', ['super_admin'])->get();
    }

// تفعيل / تعطيل
    public function toggleVerify($id)
    {
        $user = User::findOrFail($id);
        $user->is_verified = !$user->is_verified;
        $user->save();

        return response()->json(['message'=>'تم التعديل','data'=>$user]);
    }

// حذف
    public function deleteUser($id)
    {
        User::findOrFail($id)->delete();
        return response()->json(['message'=>'تم الحذف']);
    }

}
