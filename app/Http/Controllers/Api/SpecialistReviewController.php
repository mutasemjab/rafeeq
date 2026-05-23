<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Appointment;
use App\Models\Specialist;
use App\Models\SpecialistReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpecialistReviewController extends Controller
{
    public function index(Specialist $specialist): JsonResponse
    {
        $reviews = $specialist->reviews()
            ->where('status', 'published')
            ->latest()
            ->paginate(10);

        return response()->json([
            'data' => ReviewResource::collection($reviews->items()),
            'meta' => ['total' => $reviews->total()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'appointment_id' => 'required|exists:appointments,id',
            'rating'         => 'required|integer|min:1|max:5',
            'review'         => 'nullable|string|max:2000',
        ]);

        $appointment = Appointment::findOrFail($data['appointment_id']);
        abort_unless($appointment->user_id === $request->user()->id, 403);
        abort_unless($appointment->status === 'completed', 422, 'Can only review completed appointments.');

        $review = SpecialistReview::create([
            'appointment_id' => $appointment->id,
            'user_id'        => $request->user()->id,
            'specialist_id'  => $appointment->specialist_id,
            'rating'         => $data['rating'],
            'review'         => $data['review'] ?? null,
            'status'         => 'pending',
        ]);

        // Update specialist rating
        $specialist = $appointment->specialist;
        $avg = $specialist->reviews()->where('status', 'published')->avg('rating');
        $count = $specialist->reviews()->where('status', 'published')->count();
        $specialist->update(['rating_avg' => $avg ?? 0, 'reviews_count' => $count]);

        return response()->json(new ReviewResource($review), 201);
    }
}
