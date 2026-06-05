<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\AppointmentsController;
use App\Models\Admin;
use App\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminAppointmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_meeting_link_from_appointment_screen(): void
    {
        $appointment = Appointment::factory()->create([
            'status' => 'confirmed',
            'join_url' => null,
            'join_available_at' => null,
        ]);

        $request = Request::create('/admin/appointments/'.$appointment->id.'/status', 'POST', [
            'status' => 'upcoming',
            'join_url' => 'https://meet.google.com/test-room',
            'join_available_at' => '2026-06-12 14:30:00',
        ]);

        $response = app(AppointmentsController::class)->updateStatus($request, $appointment);

        $this->assertSame(302, $response->getStatusCode());

        $appointment->refresh();

        $this->assertSame('upcoming', $appointment->status);
        $this->assertSame('https://meet.google.com/test-room', $appointment->join_url);
        $this->assertSame('2026-06-12 14:30:00', $appointment->join_available_at?->format('Y-m-d H:i:s'));
    }

    public function test_admin_appointment_view_renders_meeting_link_inputs(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Admin User',
            'email' => 'admin+'.uniqid().'@example.com',
            'username' => 'admin_'.uniqid(),
            'password' => bcrypt('secret'),
        ]);

        $appointment = Appointment::factory()->create();

        auth()->shouldUse('admin');
        $this->actingAs($admin, 'admin');
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());

        $view = app(AppointmentsController::class)->show($appointment);
        $html = $view->render();

        $this->assertStringContainsString('name="join_url"', $html);
        $this->assertStringContainsString('name="join_available_at"', $html);
    }
}
