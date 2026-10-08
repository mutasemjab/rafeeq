<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $conversations = $request->user()
            ->conversations()
            ->where('is_temporary', false)
            ->orderByDesc('updated_at')->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'data' => ConversationResource::collection($conversations->items()),
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'total' => $conversations->total(),
            ],
        ]);
    }

    public function store(StoreConversationRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (isset($data['temporary_subject'])) {
            abort_unless($request->boolean('is_temporary') && $request->user()->hasAiConsent(), 422, 'Temporary subject data requires a temporary conversation and AI consent.');
        }

        if (isset($data['child_id'])) {
            $child = $request->user()->children()->find($data['child_id']);
            abort_unless($child, 403, 'Not your child.');
        }

        $person = null;
        if (isset($data['person_profile_id'])) {
            $person = $request->user()->personProfiles()->find($data['person_profile_id']);
            abort_unless($person && $person->hasProcessingConsent(), 403, 'Person consent or access is unavailable.');
            if ($person->legacy_child_id !== null) {
                abort_unless($request->user()->children()->whereKey($person->legacy_child_id)->exists(), 403);
            }
        }

        $conversation = $request->user()->conversations()->create([
            'child_id' => $person?->legacy_child_id ?? ($data['child_id'] ?? null),
            'person_profile_id' => $person?->id,
            'is_temporary' => $request->boolean('is_temporary'),
            'expires_at' => $request->boolean('is_temporary') ? now()->addMinutes((int) config('privacy.temporary_conversation_retention_minutes', 60)) : null,
            'temporary_subject' => $data['temporary_subject'] ?? null,
            'title' => $data['title'] ?? null,
            'source' => Conversation::normalizeSource($data['source'] ?? null),
            'status' => 'active',
        ]);

        return response()->json(new ConversationResource($conversation), 201);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $messages = $conversation->messages()
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'conversation' => new ConversationResource($conversation),
            'messages' => MessageResource::collection($messages),
        ]);
    }

    public function destroy(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('delete', $conversation);
        $conversation->delete();

        return response()->json(['message' => 'Conversation deleted.']);
    }
}
