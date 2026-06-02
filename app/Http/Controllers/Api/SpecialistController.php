<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SpecialistResource;
use App\Models\Specialist;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpecialistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $specialists = Specialist::where('is_active', true)
            ->when($request->input('language'), fn($query, $language) => $query->whereJsonContains('metadata->languages', $language))
            ->when($request->input('specialization'), function ($query, $specialization) {
                $query->where(function ($query) use ($specialization) {
                    $query->where('specialty', 'like', "%{$specialization}%")
                        ->orWhereJsonContains('metadata->specializations', $specialization);
                });
            })
            ->orderByDesc('rating_avg')
            ->paginate(15);

        return response()->json([
            'data' => SpecialistResource::collection($specialists->items()),
            'meta' => ['total' => $specialists->total(), 'last_page' => $specialists->lastPage()],
        ]);
    }

    public function show(Specialist $specialist): JsonResponse
    {
        return response()->json(new SpecialistResource($specialist));
    }

    public function availabilities(Request $request, Specialist $specialist): JsonResponse
    {
        $data = $request->validate([
            'date' => 'nullable|date',
        ]);

        $date = Carbon::parse($data['date'] ?? today()->toDateString())->toDateString();

        $slots = $specialist->availabilities()
            ->whereDate('available_date', $date)
            ->where('is_available', true)
            ->orderBy('start_time')
            ->get()
            ->map(fn($slot) => [
                'available_date'      => $slot->available_date?->toDateString(),
                'day_of_week'         => $slot->available_date?->dayOfWeek,
                'start_time'          => $slot->start_time,
                'end_time'            => $slot->end_time,
                'slot_duration_minutes' => $slot->slot_duration_minutes,
                'capacity'            => $slot->capacity,
            ]);

        return response()->json(['date' => $date, 'slots' => $slots]);
    }
}
