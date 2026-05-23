<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UploadChatAttachmentRequest;
use App\Http\Resources\ChatAttachmentResource;
use App\Jobs\ProcessChatAttachmentJob;
use App\Models\ChatAttachment;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatAttachmentController extends Controller
{
    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);
        $attachments = ChatAttachment::where('conversation_id', $conversation->id)
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json(ChatAttachmentResource::collection($attachments));
    }

    public function store(UploadChatAttachmentRequest $request): JsonResponse
    {
        $conversationId = $request->input('conversation_id');
        $conversation   = Conversation::findOrFail($conversationId);
        $this->authorize('view', $conversation);

        $user = $request->user();

        // Max 5 attachments per conversation
        $count = ChatAttachment::where('conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->count();

        if ($count >= config('ai.max_chat_attachments_per_conversation', 5)) {
            return response()->json(['message' => 'Maximum attachments per conversation reached.'], 422);
        }

        $file = $request->file('file');
        $path = $file->store("chat-attachments/{$user->id}/{$conversationId}", 'public');

        $attachment = ChatAttachment::create([
            'user_id'         => $user->id,
            'conversation_id' => $conversationId,
            'child_id'        => $conversation->child_id,
            'file_path'       => $path,
            'original_name'   => $file->getClientOriginalName(),
            'mime_type'       => $file->getMimeType(),
            'file_size'       => $file->getSize(),
            'status'          => 'pending',
        ]);

        ProcessChatAttachmentJob::dispatch($attachment->id);

        return response()->json(new ChatAttachmentResource($attachment), 201);
    }

    public function destroy(Request $request, ChatAttachment $attachment): JsonResponse
    {
        $this->authorize('delete', $attachment);
        $attachment->delete();
        return response()->json(['message' => 'Attachment deleted.']);
    }
}
