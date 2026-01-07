<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

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
}
