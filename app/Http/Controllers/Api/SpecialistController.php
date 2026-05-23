<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SpecialistResource;
use App\Models\Specialist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpecialistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $specialists = Specialist::where('is_active', true)
            ->when($request->input('language'), fn($q, $lang) => $q->where('languages', 'like', "%{$lang}%"))
            ->when($request->input('specialization'), fn($q, $s) => $q->where('specializations', 'like', "%{$s}%"))
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
        $date = $request->input('date', today()->toDateString());

        $slots = $specialist->availabilities()
            ->where('day_of_week', now()->parse($date)->dayOfWeek)
            ->where('is_active', true)
            ->get()
            ->map(fn($slot) => [
                'day_of_week'         => $slot->day_of_week,
                'start_time'          => $slot->start_time,
                'end_time'            => $slot->end_time,
                'slot_duration_minutes' => $slot->slot_duration_minutes,
            ]);

        return response()->json(['date' => $date, 'slots' => $slots]);
    }
}
