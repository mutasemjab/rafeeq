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
        $data = $request->validate([
            'search' => 'nullable|string|max:200', 'specialization' => 'nullable|string|max:100',
            'language' => 'nullable|string|max:50', 'page' => 'nullable|integer|min:1',
        ]);
        $search = trim((string) ($data['search'] ?? ''));
        $category = mb_strtolower(trim((string) ($data['specialization'] ?? '')));
        $aliases = match ($category) {
            'speech', 'النطق', 'التخاطب' => ['speech', 'language', 'نطق', 'تخاطب'],
            'behaviour', 'behavior', 'السلوك', 'سلوك' => ['behaviour', 'behavior', 'سلوك'],
            'psychology', 'علم النفس', 'نفسي' => ['psychology', 'psychologist', 'psychological', 'نفس'],
            default => $category === '' ? [] : [$category],
        };
        $specialists = Specialist::where('is_active', true)
            ->when($data['language'] ?? null, fn ($query, $language) => $query->whereJsonContains('metadata->languages', $language))
            ->when($search !== '', function ($query) use ($search) {
                // Bind literal user text: SQL wildcard characters are not filters.
                $search = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
                $query->where(function ($query) use ($search) {
                    foreach (['display_name', 'first_name', 'last_name', 'title', 'specialty', 'city', 'country'] as $column) {
                        $query->orWhereRaw("LOWER({$column}) LIKE ? ESCAPE '!'", ['%'.mb_strtolower($search).'%']);
                    }
                });
            })
            ->when($aliases !== [], function ($query) use ($aliases) {
                $query->where(function ($query) use ($aliases) {
                    foreach ($aliases as $alias) {
                        $query->orWhereRaw('LOWER(specialty) LIKE ?', ["%{$alias}%"])
                            ->orWhereRaw('LOWER(title) LIKE ?', ["%{$alias}%"])
                            ->orWhereRaw("LOWER(JSON_EXTRACT(metadata, '$.specializations')) LIKE ?", ['%'.$alias.'%']);
                    }
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
