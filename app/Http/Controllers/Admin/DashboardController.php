<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $usersCount  = User::count();
        $appointmentsCount = Appointment::count();
        $recentUsers = User::latest()->take(5)->get();

        return view('admin.dashboard', compact('usersCount', 'appointmentsCount', 'recentUsers'));
    }
}
