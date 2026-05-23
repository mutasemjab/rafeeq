<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Specialist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AppointmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $appointments = $request->user()
            ->appointments()
            ->with('specialist')
            ->latest()
            ->paginate(15);

        return response()->json([
            'data' => AppointmentResource::collection($appointments->items()),
            'meta' => ['total' => $appointments->total(), 'last_page' => $appointments->lastPage()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'specialist_id'    => 'required|exists:specialists,id',
            'child_id'         => 'nullable|exists:children,id',
            'appointment_type' => 'nullable|string|max:100',
            'scheduled_date'   => 'required|date|after_or_equal:today',
            'start_time'       => 'required|date_format:H:i',
            'end_time'         => 'required|date_format:H:i|after:start_time',
            'timezone'         => 'nullable|string|max:50',
            'notes'            => 'nullable|string',
        ]);

        if (isset($data['child_id'])) {
            $request->user()->children()->findOrFail($data['child_id']);
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
            'status'           => 'pending_payment',
        ]);

        return response()->json(new AppointmentResource($appointment->load('specialist')), 201);
    }

    public function show(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('view', $appointment);
        return response()->json(new AppointmentResource($appointment->load('specialist')));
    }

    public function cancel(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('cancel', $appointment);

        $data = $request->validate(['reason' => 'nullable|string|max:500']);

        $appointment->update([
            'status'          => 'canceled',
            'canceled_reason' => $data['reason'] ?? null,
        ]);

        return response()->json(['message' => 'Appointment canceled.', 'appointment' => new AppointmentResource($appointment->fresh())]);
    }
}
