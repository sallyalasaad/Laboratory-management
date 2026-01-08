<?php
namespace App\Http\Controllers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{public function login(Request $request)
{
    // تحقق إذا المرسل يحتوي email أو phone
    $request->validate([
        'email' => 'sometimes|email',
        'phone' => 'sometimes|string',
        'password' => 'required|string|min:6',
    ]);

    $user = null;

    // إذا أرسلوا إيميل
    if ($request->has('email')) {
        $user = User::where('email', $request->email)->first();
    }
    // إذا أرسلوا رقم الهاتف
    elseif ($request->has('phone')) {
        $user = User::where('phone', $request->phone)->first();
    } else {
        return response()->json(['message' => 'يجب إدخال البريد الإلكتروني أو رقم الهاتف'], 422);
    }

    // التحقق من كلمة المرور
    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json(['message' => 'بيانات غير صحيحة'], 401);
    }

    // التحقق من تفعيل الحساب
    if (!$user->is_verified) {
        return response()->json(['message' => 'الحساب غير مفعل'], 403);
    }

    // إنشاء توكن خاص بالدور
    $token = $user->createToken($user->role . '_token')->plainTextToken;

    return response()->json([
        'message' => 'تم تسجيل الدخول بنجاح ✅',
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role
        ],
        'access_token' => $token,
    ]);
}





    public function sendResetPasswordOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'البريد الإلكتروني غير موجود'], 404);
        }

        $otp = rand(100000, 999999);

        $user->otp = $otp;
        $user->otp_created_at = now();
        $user->save();

        Mail::raw("رمز إعادة تعيين كلمة المرور الخاص بك هو: $otp", function ($message) use ($user) {
            $message->to($user->email)
                ->subject('رمز إعادة تعيين كلمة المرور');
        });

        return response()->json(['message' => 'تم إرسال رمز إعادة تعيين كلمة المرور إلى بريدك الإلكتروني.']);
    }


    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = User::where('email', $request->email)
            ->where('otp', $request->otp)
            ->first();

        if (!$user) {
            return response()->json(['message' => 'رمز التحقق غير صحيح أو منتهي.'], 400);
        }

        // تحقق صلاحية OTP لمدة 15 دقيقة فقط
        $expiresAt = Carbon::parse($user->otp_created_at)->addMinutes(15);        if (now()->greaterThan($expiresAt)) {
            return response()->json(['message' => 'رمز التحقق منتهي الصلاحية.'], 400);
        }

        $user->password = Hash::make($request->password);
        $user->otp = null;
        $user->otp_created_at = null;
        $user->save();

        return response()->json(['message' => 'تم تحديث كلمة المرور بنجاح']);
    }


}
