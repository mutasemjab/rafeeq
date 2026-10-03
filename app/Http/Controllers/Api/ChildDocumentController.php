<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UploadChildDocumentRequest;
use App\Http\Resources\ChildDocumentResource;
use App\Jobs\ProcessChildDocumentJob;
use App\Models\Child;
use App\Models\ChildDocument;
use App\Services\Documents\PrivateDocumentStorage;
use App\Services\Documents\PrivateFileProcessingDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ChildDocumentController extends Controller
{
    public function index(Request $request, Child $child): JsonResponse
    {
        $this->authorize('view', $child);
        $docs = $child->documents()->where('user_id', $request->user()->id)->latest()->get();

        return response()->json(ChildDocumentResource::collection($docs));
    }

    public function store(UploadChildDocumentRequest $request, Child $child): JsonResponse
    {
        $this->authorize('update', $child);

        $file = $request->file('file');
        $path = $file->store("children/{$child->id}/documents", 'private');

        $doc = ChildDocument::create([
            'child_id' => $child->id,
            'user_id' => $request->user()->id,
            'title' => $request->input('title', $file->getClientOriginalName()),
            'category' => $request->input('document_type', 'other'),
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'status' => 'uploaded',
            'storage_disk' => 'private',
        ]);

        if ($request->user()->hasAiConsent()) {
            app(PrivateFileProcessingDispatcher::class)->dispatch(ProcessChildDocumentJob::class, (int) $doc->id);
        }

        return response()->json(new ChildDocumentResource($doc), 201);
    }

    public function destroy(Request $request, Child $child, ChildDocument $document): JsonResponse
    {
        $this->authorize('update', $child);
        abort_unless((int) $document->child_id === (int) $child->id && (int) $document->user_id === (int) $request->user()->id, 404);
        DB::transaction(function () use ($document): void {
            $locked = ChildDocument::query()->lockForUpdate()->findOrFail($document->id);
            app(PrivateDocumentStorage::class)->delete($locked);
            $metadata = is_array($locked->metadata) ? $locked->metadata : [];
            unset($metadata['extracted_text'], $metadata['extracted_text_length'], $metadata['extracted_text_truncated']);
            $locked->update(['metadata' => $metadata]);
            $locked->delete();
        });

        return response()->json(['message' => 'Document deleted.']);
    }

    public function download(Request $request, Child $child, ChildDocument $document): BinaryFileResponse
    {
        $this->authorize('view', $child);
        abort_unless((int) $document->child_id === (int) $child->id && (int) $document->user_id === (int) $request->user()->id, 404);

        return app(PrivateDocumentStorage::class)->download($document);
    }

    public function downloadSigned(Request $request, ChildDocument $document): BinaryFileResponse
    {
        abort_unless($request->hasValidSignature() && (int) $request->query('owner') === (int) $document->user_id, 403);
        abort_unless($document->child()->where('user_id', $document->user_id)->exists(), 404);

        return app(PrivateDocumentStorage::class)->download($document);
    }
}
