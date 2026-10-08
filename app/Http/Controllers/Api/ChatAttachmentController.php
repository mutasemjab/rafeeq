<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UploadChatAttachmentRequest;
use App\Http\Resources\ChatAttachmentResource;
use App\Jobs\ProcessChatAttachmentJob;
use App\Models\ChatAttachment;
use App\Models\Conversation;
use App\Services\Documents\PrivateDocumentStorage;
use App\Services\Documents\PrivateFileProcessingDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
        $conversation = Conversation::findOrFail($conversationId);
        $this->authorize('view', $conversation);

        $user = $request->user();
        app(\App\Services\AI\PersonContextService::class)->assertCanProcess($conversation, (int) $user->id);

        // Max 5 attachments per conversation
        $count = ChatAttachment::where('conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->count();

        if ($count >= config('ai.max_chat_attachments_per_conversation', 5)) {
            return response()->json(['message' => 'Maximum attachments per conversation reached.'], 422);
        }

        $file = $request->file('file');
        $path = $file->store("chat-attachments/{$user->id}/{$conversationId}", 'private');

        $attachment = ChatAttachment::create([
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'child_id' => $conversation->child_id,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'status' => 'uploaded',
            'storage_disk' => 'private',
        ]);

        app(PrivateFileProcessingDispatcher::class)->dispatch(ProcessChatAttachmentJob::class, (int) $attachment->id);

        return response()->json(new ChatAttachmentResource($attachment), 201);
    }

    public function destroy(Request $request, ChatAttachment $attachment): JsonResponse
    {
        $this->authorize('delete', $attachment);
        DB::transaction(function () use ($attachment): void {
            $locked = ChatAttachment::query()->lockForUpdate()->findOrFail($attachment->id);
            app(PrivateDocumentStorage::class)->delete($locked);
            $locked->chunks()->delete();
            $locked->delete();
        });

        return response()->json(['message' => 'Attachment deleted.']);
    }

    public function retry(Request $request, ChatAttachment $attachment): JsonResponse
    {
        $this->authorize('view', $attachment);
        $this->requireActiveOwnerContext($attachment);
        abort_unless($request->user()->hasAiConsent(), 403, 'AI data-sharing consent is required.');
        app(\App\Services\AI\PersonContextService::class)->assertCanProcess($attachment->conversation, (int) $request->user()->id);
        $claimed = ChatAttachment::query()->whereKey($attachment->id)->where('status', 'failed')->update([
            'status' => 'uploaded', 'processing_error' => null, 'processed_at' => null,
        ]);
        if (! $claimed) {
            return response()->json(['message' => 'Only failed attachments can be retried.'], 409);
        }
        app(PrivateFileProcessingDispatcher::class)->dispatch(ProcessChatAttachmentJob::class, (int) $attachment->id);

        return response()->json(new ChatAttachmentResource($attachment->fresh()));
    }

    public function download(Request $request, ChatAttachment $attachment): BinaryFileResponse
    {
        $this->authorize('view', $attachment);
        $this->requireActiveOwnerContext($attachment);

        return app(PrivateDocumentStorage::class)->download($attachment);
    }

    public function downloadSigned(Request $request, ChatAttachment $attachment): BinaryFileResponse
    {
        abort_unless($request->hasValidSignature() && (int) $request->query('owner') === (int) $attachment->user_id, 403);
        $this->requireActiveOwnerContext($attachment);

        return app(PrivateDocumentStorage::class)->download($attachment);
    }

    private function requireActiveOwnerContext(ChatAttachment $attachment): void
    {
        abort_unless($attachment->conversation()->where('user_id', $attachment->user_id)->exists(), 404);
        if ($attachment->child_id !== null) {
            abort_unless($attachment->child()->where('user_id', $attachment->user_id)->exists(), 404);
        }
    }
}
