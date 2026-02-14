<?php

namespace App\Services;

use App\DAO\UserDAO;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserService
{
    protected $dao;

    public function __construct(UserDAO $dao)
    {
        $this->dao = $dao;
    }

    /**
     * إنشاء موظف
     */
    public function createEmployee(array $data)
    {
        $data['password'] = Hash::make($data['password']);
        $data['is_verified'] = true;

        $user = $this->dao->create($data);
        $user->assignRole($data['role']);

        return $user;
    }

    /**
     * جلب جميع الموظفين
     */
    public function getEmployees()
    {
        return $this->dao->allEmployees();
    }

    /**
     * تفعيل / تعطيل مستخدم
     */
    public function toggleVerify($id, User $authUser)
    {
        $user = $this->dao->findById($id);
        if (!$user) abort(404, 'المستخدم غير موجود');

        if ($authUser->id === $user->id) abort(403, 'لا يمكنك تعطيل حسابك');
        if ($authUser->hasRole('admin') && $user->hasAnyRole(['admin','super_admin'])) {
            abort(403, 'لا يمكنك تعديل حساب إداري');
        }
        if ($authUser->hasRole('super_admin') && $user->hasRole('super_admin')) {
            abort(403, 'لا يمكنك تعطيل Super Admin');
        }

        $user->is_verified = !$user->is_verified;
        $user->save();

        return $user;
    }

    /**
     * حذف مستخدم
     */
    public function deleteUser($id)
    {
        $this->dao->delete($id);
    }

    /**
     * تعديل بيانات موظف
     */
    public function updateEmployee($id, array $data)
    {
        $user = $this->dao->findById($id);
        if (!$user) abort(404, 'المستخدم غير موجود');

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user = $this->dao->update($user, $data);

        if (isset($data['role'])) {
            $user->syncRoles([$data['role']]);
        }

        return $user;
    }
}
