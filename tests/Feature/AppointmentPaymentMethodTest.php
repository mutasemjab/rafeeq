<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AppointmentController;
use App\Models\Appointment;
use App\Models\AppSetting;
use App\Models\Child;
use App\Models\Payment;
use App\Models\Specialist;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
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

    public function test_user_can_list_appointments(): void
    {
        $user = User::factory()->create();
        $specialist = Specialist::factory()->create([
            'is_active' => true,
        ]);

        Appointment::factory()->create([
            'user_id' => $user->id,
            'specialist_id' => $specialist->id,
        ]);

        $request = Request::create('/api/v1/appointments', 'GET', ['page' => 1]);
        $request->setUserResolver(fn() => $user);

        $response = app(AppointmentController::class)->index($request);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $payload['data']);
        $this->assertSame(1, $payload['meta']['total']);
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

    public function test_card_payment_is_rejected_when_mobile_payments_are_disabled(): void
    {
        AppSetting::query()->create([
            'key' => 'mobile_payments_enabled',
            'value' => '0',
        ]);

        $user = User::factory()->create();
        $specialist = Specialist::factory()->create([
            'is_active' => true,
        ]);

        $request = Request::create('/api/v1/appointments', 'POST', [
            'specialist_id' => $specialist->id,
            'scheduled_date' => now()->addDays(3)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '10:30',
        ]);
        $request->setUserResolver(fn() => $user);

        try {
            app(AppointmentController::class)->store($request);
            $this->fail('Expected card payment validation to fail when mobile payments are disabled.');
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

    public function test_user_can_update_own_appointment_details(): void
    {
        $user = User::factory()->create();
        $child = Child::query()->create([
            'user_id' => $user->id,
            'name' => 'Maya',
            'birth_date' => '2020-06-01',
        ]);
        $specialist = Specialist::factory()->create([
            'is_active' => true,
        ]);
        $appointment = Appointment::factory()->create([
            'user_id' => $user->id,
            'specialist_id' => $specialist->id,
            'scheduled_date' => now()->addDays(4)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'status' => 'confirmed',
            'notes' => 'Before update',
        ]);

        $this->actingAs($user);

        $request = Request::create('/api/v1/appointments/'.$appointment->id, 'PUT', [
            'child_id' => $child->id,
            'scheduled_date' => now()->addDays(6)->toDateString(),
            'start_time' => '12:00:00',
            'end_time' => '12:45:00',
            'timezone' => 'Asia/Dubai',
            'notes' => 'Rescheduled from mobile',
        ]);
        $request->setUserResolver(fn() => $user);

        $response = app(AppointmentController::class)->update($request, $appointment);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($child->id, $payload['child_id']);
        $this->assertSame(now()->addDays(6)->toDateString(), $payload['scheduled_date']);
        $this->assertSame('12:00:00', $payload['start_time']);
        $this->assertSame('12:45:00', $payload['end_time']);
        $this->assertSame('Asia/Dubai', $payload['timezone']);
        $this->assertSame('Rescheduled from mobile', $payload['notes']);

        $appointment->refresh();

        $this->assertSame($child->id, $appointment->child_id);
        $this->assertSame(now()->addDays(6)->toDateString(), $appointment->scheduled_date?->toDateString());
        $this->assertSame('12:00:00', $appointment->start_time);
        $this->assertSame('12:45:00', $appointment->end_time);
        $this->assertSame('Asia/Dubai', $appointment->timezone);
        $this->assertSame('Rescheduled from mobile', $appointment->notes);
        $this->assertSame($specialist->id, $appointment->specialist_id);
        $this->assertSame('confirmed', $appointment->status);
    }

    public function test_user_cannot_update_completed_appointment(): void
    {
        $user = User::factory()->create();
        $specialist = Specialist::factory()->create([
            'is_active' => true,
        ]);
        $appointment = Appointment::factory()->create([
            'user_id' => $user->id,
            'specialist_id' => $specialist->id,
            'status' => 'completed',
        ]);

        $this->actingAs($user);

        $request = Request::create('/api/v1/appointments/'.$appointment->id, 'PUT', [
            'notes' => 'This should fail',
        ]);
        $request->setUserResolver(fn() => $user);

        $this->expectException(AuthorizationException::class);

        app(AppointmentController::class)->update($request, $appointment);
    }
}
