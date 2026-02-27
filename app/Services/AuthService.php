<?php

namespace App\Services;

use App\DAO\UserDAO;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;


class AuthService
{
    protected $dao;

    public function __construct(UserDAO $dao)
    {
        $this->dao = $dao;
    }

    /**
     * تسجيل الدخول
     */
    public function login(array $data)
    {
        $email = isset($data['email']) ? $data['email'] : null;
        $phone = isset($data['phone']) ? $data['phone'] : null;

        $user = $this->dao->findByEmailOrPhone($email, $phone);

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return ['message' => 'بيانات غير صحيحة', 'status' => 401];
        }

        if (!$user->is_verified) {
            return ['message' => 'الحساب غير مفعل', 'status' => 403];
        }

        $roles = $user->getRoleNames();
        $role = (count($roles) > 0) ? $roles[0] : 'user';

        $token = $user->createToken($role . '_token')->plainTextToken;

        return [
            'message' => 'تم تسجيل الدخول بنجاح ✅',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'roles' => $roles,
            ],
            'access_token' => $token
        ];
    }

    /**
     * إرسال OTP لإعادة تعيين كلمة المرور
     */
    public function sendResetPasswordOtp($email)
    {
        $user = $this->dao->findByEmail($email);
        if (!$user) {
            return ['message' => 'البريد الإلكتروني غير موجود', 'status' => 404];
        }

        $otp = rand(100000, 999999);
        $user->otp = $otp;
        $user->otp_created_at = now();
        $user->save();

        Mail::raw("رمز إعادة تعيين كلمة المرور الخاص بك هو: $otp", function ($message) use ($user) {
            $message->to($user->email)->subject('رمز إعادة تعيين كلمة المرور');
        });

        return ['message' => 'تم إرسال رمز إعادة تعيين كلمة المرور إلى بريدك الإلكتروني.'];
    }

    /**
     * إعادة تعيين كلمة المرور
     */
    public function resetPassword(array $data)
    {
        $user = $this->dao->findByEmailAndOtp($data['email'], $data['otp']);
        if (!$user) {
            return ['message' => 'رمز التحقق غير صحيح أو منتهي.', 'status' => 400];
        }

        $expiresAt = Carbon::parse($user->otp_created_at)->addMinutes(15);
        if (now()->greaterThan($expiresAt)) {
            return ['message' => 'رمز التحقق منتهي الصلاحية.', 'status' => 400];
        }

        $user->password = Hash::make($data['password']);
        $user->otp = null;
        $user->otp_created_at = null;
        $user->save();

        return ['message' => 'تم تحديث كلمة المرور بنجاح'];
    }
}

