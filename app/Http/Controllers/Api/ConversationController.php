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
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => ConversationResource::collection($conversations->items()),
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page'    => $conversations->lastPage(),
                'total'        => $conversations->total(),
            ],
        ]);
    }

    public function store(StoreConversationRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['child_id'])) {
            $child = $request->user()->children()->find($data['child_id']);
            abort_unless($child, 403, 'Not your child.');
        }

        $conversation = $request->user()->conversations()->create([
            'child_id' => $data['child_id'] ?? null,
            'title'    => $data['title'] ?? null,
            'source'   => $data['source'] ?? 'mobile',
            'status'   => 'active',
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
            'messages'     => MessageResource::collection($messages),
        ]);
    }

    public function destroy(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('delete', $conversation);
        $conversation->delete();
        return response()->json(['message' => 'Conversation deleted.']);
    }
}
