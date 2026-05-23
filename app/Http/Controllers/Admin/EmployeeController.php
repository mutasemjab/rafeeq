<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Gate;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!Gate::allows('employee-table')) {
            return redirect()->back()->with('error', __('messages.Access Denied'));
        }

        $query = Admin::where('is_super', 0)->with('roles');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'LIKE', "%{$request->search}%")
                  ->orWhere('email', 'LIKE', "%{$request->search}%")
                  ->orWhere('username', 'LIKE', "%{$request->search}%");
            });
        }

        $data = $query->paginate(10);
        return view('admin.employee.index', compact('data'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (!Gate::allows('employee-add')) {
            return redirect()->back()->with('error', __('messages.Access Denied'));
        }

        $roles = Role::where('guard_name', 'admin')->get();
        return view('admin.employee.create', compact('roles'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (!Gate::allows('employee-add')) {
            return redirect()->back()->with('error', __('messages.Access Denied'));
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email',
            'username' => 'nullable|string|max:255|unique:admins,username',
            'password' => 'required|string|min:6',
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,id'
        ]);

        DB::beginTransaction();
        try {
            $admin = Admin::create([
                'name' => $request->name,
                'email' => $request->email,
                'username' => $request->username,
                'password' => Hash::make($request->password),
                'is_super' => 0
            ]);

            $admin->syncRoles($request->roles);

            DB::commit();
            return redirect()->route('admin.employee.index')
                ->with('success', __('messages.Employee created successfully'));
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Employee creation failed: " . $e->getMessage());
            return redirect()->back()
                ->withErrors(__('messages.Something went wrong'))
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (!Gate::allows('employee-table')) {
            return redirect()->back()->with('error', __('messages.Access Denied'));
        }

        $admin = Admin::with('roles.permissions')->findOrFail($id);
        return view('admin.employee.show', compact('admin'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        if (!Gate::allows('employee-edit')) {
            return redirect()->back()->with('error', __('messages.Access Denied'));
        }

        $admin = Admin::findOrFail($id);
        $roles = Role::where('guard_name', 'admin')->get();
        $adminRoles = $admin->roles->pluck('id')->toArray();
        
        return view('admin.employee.edit', compact('admin', 'roles', 'adminRoles'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        if (!Gate::allows('employee-edit')) {
            return redirect()->back()->with('error', __('messages.Access Denied'));
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email,' . $id,
            'username' => 'nullable|string|max:255|unique:admins,username,' . $id,
            'password' => 'nullable|string|min:6',
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,id'
        ]);

        DB::beginTransaction();
        try {
            $admin = Admin::findOrFail($id);

            $admin->update([
                'name' => $request->name,
                'email' => $request->email,
                'username' => $request->username,
                'password' => $request->password ? Hash::make($request->password) : $admin->password,
            ]);

            $admin->syncRoles($request->roles);

            DB::commit();
            return redirect()->route('admin.employee.index')
                ->with('success', __('messages.Employee updated successfully'));
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Employee update failed: " . $e->getMessage());
            return redirect()->back()
                ->withErrors(__('messages.Something went wrong'))
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
  public function destroy($id)
{
    DB::beginTransaction();

    try {
        $admin = Admin::findOrFail($id);
        $admin->syncRoles([]);
        $admin->delete();
        DB::commit();

        // ✅ Return JSON if it's an AJAX call
        if (request()->ajax()) {
            return response()->json(['success' => true]);
        }

        // Otherwise normal redirect
        return redirect()->route('admin.employee.index')
            ->with('success', __('messages.Employee deleted successfully'));

    } catch (\Exception $e) {
        DB::rollBack();

        if (request()->ajax()) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return redirect()->back()->with('error', __('messages.Something went wrong'));
    }
}

}