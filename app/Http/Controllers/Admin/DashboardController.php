<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\RafiqNotification;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Payments\PaymentSettingsService;

class DashboardController extends Controller
{
    public function index()
    {
        $usersCount  = User::count();
        $appointmentsCount = Appointment::count();
        $notificationsCount = RafiqNotification::count();
        $pushDevicesCount = UserDevice::whereNotNull('push_token')->count();
        $recentUsers = User::latest()->take(5)->get();
        $paymentSettingsData = app(PaymentSettingsService::class)->snapshot();

        return view('admin.dashboard', compact(
            'usersCount',
            'appointmentsCount',
            'notificationsCount',
            'pushDevicesCount',
            'recentUsers',
            'paymentSettingsData'
        ));
    }
}
