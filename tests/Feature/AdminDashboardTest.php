<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DashboardController;
use App\Models\Admin;
use App\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_appointments_shortcut(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Admin User',
            'email' => 'admin+'.uniqid().'@example.com',
            'username' => 'admin_'.uniqid(),
            'password' => bcrypt('secret'),
        ]);

        Appointment::factory()->count(2)->create();

        auth()->shouldUse('admin');
        $this->actingAs($admin, 'admin');

        $view = app(DashboardController::class)->index();
        $html = $view->render();

        $this->assertStringContainsString('Open Appointments', $html);
        $this->assertStringContainsString(route('admin.appointments.index'), $html);
        $this->assertStringContainsString('2', $html);
    }
}
