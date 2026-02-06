<?php

namespace App\Http\Controllers;



use Illuminate\Http\Request;
use App\Services\UserService;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    protected $service;

    public function __construct(UserService $service)
    {
        $this->service = $service;
    }

    public function createEmployee(Request $request)
    {
        $data = $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'phone' => 'required|unique:users',
            'password' => 'required|min:6',
            'role' => 'required|in:raw_storekeeper,product_storekeeper,accountant,production_employee,driver',
            'contract_start_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date|after_or_equal:contract_start_date',
        ]);

        $user = $this->service->createEmployee($data);

        return response()->json(['message' => 'تم إنشاء الموظف', 'data' => $user]);
    }

    public function employees()
    {
        $users = $this->service->getEmployees();
        return response()->json(['data' => $users]);
    }

    public function toggleVerify($id)
    {
        $authUser = Auth::user();
        $user = $this->service->toggleVerify($id, $authUser);

        return response()->json(['message'=>'تم التعديل بنجاح','data'=>$user]);
    }

    public function deleteUser($id)
    {
        $this->service->deleteUser($id);
        return response()->json(['message'=>'تم الحذف']);
    }

    public function updateEmployee(Request $request, $id)
    {
        $data = $request->validate([
            'name' => 'sometimes|required',
            'email' => 'sometimes|required|email|unique:users,email,'.$id,
            'phone' => 'sometimes|required|unique:users,phone,'.$id,
            'password' => 'nullable|min:6',
            'role' => 'sometimes|required|in:raw_storekeeper,product_storekeeper,accountant,production_employee,driver',
            'contract_start_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date|after_or_equal:contract_start_date',
        ]);

        $user = $this->service->updateEmployee($id, $data);
        return response()->json(['message'=>'تم تحديث بيانات الموظف بنجاح','data'=>$user]);
    }
}
