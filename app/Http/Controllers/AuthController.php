<?php
namespace App\Http\Controllers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Services\AuthService;

class AuthController extends Controller
{
    protected $service;

    public function __construct(AuthService $service)
    {
        $this->service = $service;
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required_without:phone|email',
            'phone' => 'required_without:email|string',
            'password' => 'required|string|min:6',
        ]);

        $result = $this->service->login($data);

        return response()->json($result);
    }

    public function sendResetPasswordOtp(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $result = $this->service->sendResetPasswordOtp($request->email);
        return response()->json($result);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'otp' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        $result = $this->service->resetPassword($data);
        return response()->json($result);
    }
}
