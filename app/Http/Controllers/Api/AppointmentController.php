<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Specialist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $appointments = $request->user()
            ->appointments()
            ->with(['specialist', 'payment'])
            ->latest()
            ->paginate(15);

        return response()->json([
            'data' => AppointmentResource::collection($appointments->items()),
            'meta' => ['total' => $appointments->total(), 'last_page' => $appointments->lastPage()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $paymentMethods = ['card'];

        if ($this->payForLaterEnabled()) {
            $paymentMethods[] = 'pay_for_later';
        }

        $data = $request->validate([
            'specialist_id'    => 'required|exists:specialists,id',
            'child_id'         => 'nullable|exists:children,id',
            'appointment_type' => 'nullable|string|max:100',
            'scheduled_date'   => 'required|date|after_or_equal:today',
            'start_time'       => 'required|date_format:H:i',
            'end_time'         => 'required|date_format:H:i|after:start_time',
            'timezone'         => 'nullable|string|max:50',
            'notes'            => 'nullable|string',
            'payment_method'   => ['nullable', 'string', Rule::in($paymentMethods)],
        ]);

        if (isset($data['child_id'])) {
            $request->user()->children()->findOrFail($data['child_id']);
        }

        $paymentMethod = $data['payment_method'] ?? 'card';
        $specialist = Specialist::query()->findOrFail($data['specialist_id']);

        if ($paymentMethod === 'pay_for_later' && ! $this->payForLaterEnabled()) {
            throw ValidationException::withMessages([
                'payment_method' => ['The pay_for_later method is disabled.'],
            ]);
        }

        $appointment = Appointment::create([
            'user_id'          => $request->user()->id,
            'specialist_id'    => $data['specialist_id'],
            'child_id'         => $data['child_id'] ?? null,
            'appointment_type' => $data['appointment_type'] ?? 'general_consultation',
            'booking_reference'=> strtoupper(Str::random(10)),
            'scheduled_date'   => $data['scheduled_date'],
            'start_time'       => $data['start_time'],
            'end_time'         => $data['end_time'],
            'timezone'         => $data['timezone'] ?? 'UTC',
            'notes'            => $data['notes'] ?? null,
            'status'           => $paymentMethod === 'pay_for_later' ? 'confirmed' : 'pending_payment',
        ]);

        if ($paymentMethod === 'pay_for_later') {
            $payment = $request->user()->payments()->create([
                'payable_type' => Appointment::class,
                'payable_id' => $appointment->id,
                'payment_type' => 'appointment',
                'provider' => 'pay_for_later_test',
                'amount' => $specialist->session_fee,
                'currency' => $specialist->currency ?: 'USD',
                'status' => 'pending',
                'metadata' => [
                    'payment_method' => 'pay_for_later',
                    'test_only' => true,
                ],
            ]);

            $appointment->update([
                'payment_id' => $payment->id,
            ]);
        }

        return response()->json(new AppointmentResource($appointment->load(['specialist', 'payment'])), 201);
    }

    public function show(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('view', $appointment);
        return response()->json(new AppointmentResource($appointment->load(['specialist', 'payment'])));
    }

    public function cancel(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('cancel', $appointment);

        $data = $request->validate(['reason' => 'nullable|string|max:500']);

        $appointment->update([
            'status'          => 'canceled',
            'canceled_reason' => $data['reason'] ?? null,
        ]);

        return response()->json([
            'message' => 'Appointment canceled.',
            'appointment' => new AppointmentResource($appointment->fresh()->load(['specialist', 'payment'])),
        ]);
    }

    private function payForLaterEnabled(): bool
    {
        return (bool) config('payments.pay_for_later_enabled', false);
    }
}
