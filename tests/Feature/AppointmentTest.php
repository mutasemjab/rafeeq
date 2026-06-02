<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Specialist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    private User      $user;
    private User      $otherUser;
    private Specialist $specialist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user       = User::factory()->create();
        $this->otherUser  = User::factory()->create();
        $this->specialist = Specialist::factory()->create(['is_active' => true]);
    }

    public function test_user_can_book_appointment(): void
    {
        $this->actingAs($this->user, 'user-api')
            ->postJson('/api/v1/appointments', [
                'specialist_id' => $this->specialist->id,
                'scheduled_date'=> now()->addDays(3)->toDateString(),
                'start_time'    => '10:00',
                'end_time'      => '10:30',
            ])
            ->assertStatus(201)
            ->assertJsonPath('status', 'pending_payment');
    }

    public function test_user_cannot_view_another_users_appointment(): void
    {
        $appt = Appointment::factory()->create([
            'user_id'       => $this->otherUser->id,
            'specialist_id' => $this->specialist->id,
        ]);

        $this->actingAs($this->user, 'user-api')
            ->getJson("/api/v1/appointments/{$appt->id}")
            ->assertStatus(403);
    }

    public function test_user_can_book_appointment_with_test_pay_for_later_method(): void
    {
        $response = $this->actingAs($this->user, 'user-api')
            ->postJson('/api/v1/appointments', [
                'specialist_id' => $this->specialist->id,
                'scheduled_date'=> now()->addDays(3)->toDateString(),
                'start_time'    => '10:00',
                'end_time'      => '10:30',
                'payment_method'=> 'pay_for_later',
            ])
            ->assertStatus(201)
            ->assertJsonPath('status', 'confirmed')
            ->assertJsonPath('payment.method', 'pay_for_later')
            ->assertJsonPath('payment.provider', 'pay_for_later_test')
            ->assertJsonPath('payment.status', 'pending')
            ->assertJsonPath('payment.test_only', true);

        $appointmentId = $response->json('id');
        $appointment = Appointment::query()->findOrFail($appointmentId);
        $payment = Payment::query()->findOrFail($appointment->payment_id);

        $this->assertSame('confirmed', $appointment->status);
        $this->assertSame($this->user->id, $payment->user_id);
        $this->assertSame('appointment', $payment->payment_type);
        $this->assertSame('pay_for_later_test', $payment->provider);
        $this->assertSame('pay_for_later', data_get($payment->metadata, 'payment_method'));
        $this->assertTrue((bool) data_get($payment->metadata, 'test_only'));
    }

    public function test_pay_for_later_is_rejected_when_disabled(): void
    {
        config()->set('payments.pay_for_later_enabled', false);

        $this->actingAs($this->user, 'user-api')
            ->postJson('/api/v1/appointments', [
                'specialist_id' => $this->specialist->id,
                'scheduled_date'=> now()->addDays(3)->toDateString(),
                'start_time'    => '10:00',
                'end_time'      => '10:30',
                'payment_method'=> 'pay_for_later',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
    }

    public function test_user_can_cancel_own_appointment(): void
    {
        $appt = Appointment::factory()->create([
            'user_id'       => $this->user->id,
            'specialist_id' => $this->specialist->id,
            'status'        => 'confirmed',
        ]);

        $this->actingAs($this->user, 'user-api')
            ->postJson("/api/v1/appointments/{$appt->id}/cancel", ['reason' => 'Changed plans'])
            ->assertOk()
            ->assertJsonPath('appointment.status', 'canceled');
    }

    public function test_user_cannot_cancel_completed_appointment(): void
    {
        $appt = Appointment::factory()->create([
            'user_id'       => $this->user->id,
            'specialist_id' => $this->specialist->id,
            'status'        => 'completed',
        ]);

        $this->actingAs($this->user, 'user-api')
            ->postJson("/api/v1/appointments/{$appt->id}/cancel")
            ->assertStatus(403);
    }

    public function test_appointment_requires_future_date(): void
    {
        $this->actingAs($this->user, 'user-api')
            ->postJson('/api/v1/appointments', [
                'specialist_id' => $this->specialist->id,
                'scheduled_date'=> now()->subDay()->toDateString(),
                'start_time'    => '10:00',
                'end_time'      => '10:30',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['scheduled_date']);
    }
}
