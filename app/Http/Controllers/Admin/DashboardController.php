<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\RafiqNotification;
use App\Models\User;
use App\Models\UserDevice;

class DashboardController extends Controller
{
    public function index()
    {
        $usersCount  = User::count();
        $appointmentsCount = Appointment::count();
        $notificationsCount = RafiqNotification::count();
        $pushDevicesCount = UserDevice::whereNotNull('push_token')->count();
        $recentUsers = User::latest()->take(5)->get();

        return view('admin.dashboard', compact('usersCount', 'appointmentsCount', 'notificationsCount', 'pushDevicesCount', 'recentUsers'));
    }
}
