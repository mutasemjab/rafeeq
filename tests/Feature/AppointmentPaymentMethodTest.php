<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AppointmentController;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Specialist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AppointmentPaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    public function test_pay_for_later_creates_test_payment_and_confirms_appointment(): void
    {
        config()->set('payments.pay_for_later_enabled', true);

        $user = User::factory()->create();
        $specialist = Specialist::factory()->create([
            'is_active' => true,
            'session_fee' => 150,
            'currency' => 'AED',
        ]);

        $request = Request::create('/api/v1/appointments', 'POST', [
            'specialist_id' => $specialist->id,
            'scheduled_date' => now()->addDays(3)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '10:30',
            'payment_method' => 'pay_for_later',
        ]);
        $request->setUserResolver(fn() => $user);

        $response = app(AppointmentController::class)->store($request);
        $payload = $response->getData(true);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('confirmed', $payload['status']);
        $this->assertSame('pay_for_later', $payload['payment']['method']);
        $this->assertSame('pay_for_later_test', $payload['payment']['provider']);
        $this->assertTrue($payload['payment']['test_only']);

        $appointment = Appointment::query()->sole();
        $payment = Payment::query()->sole();

        $this->assertSame('confirmed', $appointment->status);
        $this->assertSame($payment->id, $appointment->payment_id);
        $this->assertSame($user->id, $payment->user_id);
        $this->assertSame('appointment', $payment->payment_type);
        $this->assertSame('pay_for_later_test', $payment->provider);
        $this->assertSame(150.0, $payment->amount);
        $this->assertSame('AED', $payment->currency);
        $this->assertSame('pay_for_later', data_get($payment->metadata, 'payment_method'));
        $this->assertTrue((bool) data_get($payment->metadata, 'test_only'));
    }

    public function test_pay_for_later_is_rejected_when_disabled(): void
    {
        config()->set('payments.pay_for_later_enabled', false);

        $user = User::factory()->create();
        $specialist = Specialist::factory()->create([
            'is_active' => true,
        ]);

        $request = Request::create('/api/v1/appointments', 'POST', [
            'specialist_id' => $specialist->id,
            'scheduled_date' => now()->addDays(3)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '10:30',
            'payment_method' => 'pay_for_later',
        ]);
        $request->setUserResolver(fn() => $user);

        try {
            app(AppointmentController::class)->store($request);
            $this->fail('Expected pay_for_later validation to fail when disabled.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment_method', $exception->errors());
        }
    }

    public function test_appointment_accepts_times_with_seconds_from_availability_api(): void
    {
        config()->set('payments.pay_for_later_enabled', true);

        $user = User::factory()->create();
        $specialist = Specialist::factory()->create([
            'is_active' => true,
        ]);

        $request = Request::create('/api/v1/appointments', 'POST', [
            'specialist_id' => $specialist->id,
            'scheduled_date' => now()->addDays(3)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'payment_method' => 'pay_for_later',
        ]);
        $request->setUserResolver(fn() => $user);

        $response = app(AppointmentController::class)->store($request);
        $payload = $response->getData(true);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('10:00:00', $payload['start_time']);
        $this->assertSame('10:30:00', $payload['end_time']);

        $appointment = Appointment::query()->sole();

        $this->assertSame('10:00:00', $appointment->start_time);
        $this->assertSame('10:30:00', $appointment->end_time);
    }
}
