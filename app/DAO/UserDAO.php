<?php

namespace App\DAO;

use App\Models\User;

class UserDAO
{
    // إنشاء مستخدم
    public function create(array $data)
    {
        return User::create($data);
    }

    // إيجاد مستخدم حسب ID
    public function findById($id)
    {
        return User::find($id);
    }

    // جلب كل الموظفين حسب الأدوار
    public function allEmployees()
    {
        return User::role([
            'raw_storekeeper',
            'product_storekeeper',
            'accountant',
            'production_employee',
            'driver'
        ])->orderBy('created_at', 'desc')->get();
    }

    // تحديث بيانات مستخدم
    public function update(User $user, array $data)
    {
        $user->update($data);
        return $user;
    }

    // حذف مستخدم
    public function delete($id)
    {
        User::findOrFail($id)->delete();
    }

    // إيجاد مستخدم حسب البريد أو الهاتف
    public function findByEmailOrPhone($email = null, $phone = null)
    {
        if ($email) return User::where('email', $email)->first();
        if ($phone) return User::where('phone', $phone)->first();
        return null;
    }

    // إيجاد مستخدم حسب البريد
    public function findByEmail($email)
    {
        return User::where('email', $email)->first();
    }

    // إيجاد مستخدم حسب البريد و OTP
    public function findByEmailAndOtp($email, $otp)
    {
        return User::where('email', $email)
            ->where('otp', $otp)
            ->first();
    }
}
