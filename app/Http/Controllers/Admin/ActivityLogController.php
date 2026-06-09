<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = DB::table('admin_activity_logs')
            ->join('users', 'admin_activity_logs.admin_user_id', '=', 'users.id')
            ->select('admin_activity_logs.*', 'users.name as admin_name', 'users.email as admin_email')
            ->latest('admin_activity_logs.created_at')
            ->paginate($this->paginationCount());
        return view('admin.activity.index', compact('logs'));
    }
}
