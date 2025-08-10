<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolePermissionController extends Controller
{
    public function createRole(Request $request)
    {
        $request->validate(['name' => 'required|string|unique:roles,name']);
        $role = Role::create(['name' => $request->name]);
        return response()->json(['message' => 'Role created', 'role' => $role]);
    }

    public function createPermission(Request $request)
    {
        $request->validate(['name' => 'required|string|unique:permissions,name']);
        $permission = Permission::create(['name' => $request->name]);
        return response()->json(['message' => 'Permission created', 'permission' => $permission]);
    }

    public function assignPermissionToRole(Request $request, Role $role)
    {
        $request->validate(['permission' => 'required|string|exists:permissions,name']);
        $role->givePermissionTo($request->permission);
        return response()->json(['message' => "Permission '{$request->permission}' assigned to role '{$role->name}'"]);
    }

    public function assignRoleToUser(Request $request, User $user)
    {
        $request->validate(['role' => 'required|string|exists:roles,name']);
        $user->assignRole($request->role);
        return response()->json(['message' => "Role '{$request->role}' assigned to user '{$user->id}'"]);
    }

    public function listRoles()
    {
        return response()->json(Role::all());
    }

    public function listPermissions()
    {
        return response()->json(Permission::all());
    }
}
