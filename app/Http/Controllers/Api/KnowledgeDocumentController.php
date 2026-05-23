<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UploadKnowledgeDocumentRequest;
use App\Http\Resources\KnowledgeDocumentResource;
use App\Jobs\ProcessKnowledgeDocumentJob;
use App\Models\KnowledgeDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KnowledgeDocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $docs = KnowledgeDocument::where('status', 'processed')
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => KnowledgeDocumentResource::collection($docs->items()),
            'meta' => ['total' => $docs->total(), 'last_page' => $docs->lastPage()],
        ]);
    }

    public function store(UploadKnowledgeDocumentRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->role === 'admin', 403, 'Admin only.');

        $file = $request->file('file');
        $path = $file->store('knowledge', 'public');

        $doc = KnowledgeDocument::create([
            'title'         => $request->filled('title')
                ? $request->input('title')
                : KnowledgeDocument::titleFromFilename($file->getClientOriginalName()),
            'category'      => $request->input('category'),
            'file_path'     => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getMimeType(),
            'file_size'     => $file->getSize(),
            'status'        => 'uploaded',
            'uploaded_by'   => $user->id,
        ]);

        ProcessKnowledgeDocumentJob::dispatchWithSyncFallback($doc->id);

        return response()->json(new KnowledgeDocumentResource($doc), 201);
    }

    public function destroy(Request $request, KnowledgeDocument $knowledgeDocument): JsonResponse
    {
        abort_unless($request->user()->role === 'admin', 403, 'Admin only.');
        $knowledgeDocument->delete();
        return response()->json(['message' => 'Document deleted.']);
    }
}
