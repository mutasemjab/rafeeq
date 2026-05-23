<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
class RoleController extends Controller
{

    public function index(Request $request)
    {
         if (!auth('admin')->user()->can('roles-index')) {
             abort(403);
         }

         $data = Role::paginate(10);
         return view('admin.roles.index', compact('data'));
    }


    public function create()
    {
        $permissions = Permission::where('guard_name', 'admin')->get();

        $groupedPermissions = $permissions->groupBy(function ($perm) {
            // Split by '-' and remove the last part (action: index, create, edit, delete)
            $parts = explode('-', $perm->name);
            array_pop($parts); // Remove last element (action)
            return implode('-', $parts); // Join back: 'about-statistics', 'service-details', etc.
        });

        return view('admin.roles.create', compact('groupedPermissions'));
    }


    public function store(Request $request)
    {
        $request->validate([
            'name'  => 'required|string|max:255|unique:roles,name',
            'perms' => 'nullable|array',
            'perms.*' => 'exists:permissions,id',
        ], [
            'name.required' => __('messages.role_name') . ' ' . __('messages.required'),
            'name.unique' => __('messages.role_name') . ' مستخدم من قبل',
        ]);

        DB::beginTransaction();
        try {

            $role = Role::create([
                'name'       => $request->name,
                'guard_name' => 'admin',
            ]);

            // Sync permissions if provided
            if ($request->has('perms') && is_array($request->perms)) {
                $role->syncPermissions($request->perms);
            }

            DB::commit();
            return redirect()->route('admin.role.index')->with('success', __('messages.success'));
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Role Store Error: ' . $e->getMessage());

            return back()->withErrors(__('messages.server_error'))->withInput();
        }
    }


    public function edit($id)
    {
        $role = Role::findOrFail($id);

        $permissions = Permission::where('guard_name', 'admin')->get();
        $role_permissions = $role->permissions->pluck('id')->toArray();


        $groupedPermissions = $permissions->groupBy(function ($perm) {
            // Split by '-' and remove the last part (action: index, create, edit, delete)
            $parts = explode('-', $perm->name);
            array_pop($parts); // Remove last element (action)
            return implode('-', $parts); // Join back: 'about-statistics', 'service-details', etc.
        });

        return view('admin.roles.edit', compact('groupedPermissions', 'role_permissions', 'role'));
    }


    public function update(Request $request, $id)
    {
        $request->validate([
            'name'  => 'required|string|max:255|unique:roles,name,' . $id,
            'perms' => 'nullable|array',
            'perms.*' => 'exists:permissions,id',
        ], [
            'name.required' => __('messages.role_name') . ' ' . __('messages.required'),
            'name.unique' => __('messages.role_name') . ' مستخدم من قبل',
        ]);

        DB::beginTransaction();
        try {
            $role = Role::findOrFail($id);

            $role->update([
                'name' => $request->name,
            ]);

            // Sync permissions - if no permissions selected, it will remove all permissions
            if ($request->has('perms') && is_array($request->perms)) {
                $role->syncPermissions($request->perms);
            } else {
                // Remove all permissions if none selected
                $role->syncPermissions([]);
            }

            DB::commit();

            return redirect()
                ->route('admin.role.index')
                ->with('success', __('messages.success'));

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Role Update Error: '.$e->getMessage());

            return back()->withErrors(__('messages.server_error'))->withInput();
        }
    }


    public function delete(Request $request)
    {
        Role::where('id',$request->id)->delete();
       return redirect()->route('admin.role.index');
    }
}