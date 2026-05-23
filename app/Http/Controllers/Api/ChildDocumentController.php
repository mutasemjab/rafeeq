<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UploadChildDocumentRequest;
use App\Http\Resources\ChildDocumentResource;
use App\Models\Child;
use App\Models\ChildDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChildDocumentController extends Controller
{
    public function index(Request $request, Child $child): JsonResponse
    {
        $this->authorize('view', $child);
        $docs = $child->documents()->latest()->get();
        return response()->json(ChildDocumentResource::collection($docs));
    }

    public function store(UploadChildDocumentRequest $request, Child $child): JsonResponse
    {
        $this->authorize('update', $child);

        $file = $request->file('file');
        $path = $file->store("children/{$child->id}/documents", 'public');

        $doc = ChildDocument::create([
            'child_id'      => $child->id,
            'user_id'       => $request->user()->id,
            'title'         => $request->input('title', $file->getClientOriginalName()),
            'document_type' => $request->input('document_type', 'other'),
            'file_path'     => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getMimeType(),
            'file_size'     => $file->getSize(),
            'status'        => 'pending',
        ]);

        return response()->json(new ChildDocumentResource($doc), 201);
    }

    public function destroy(Request $request, Child $child, ChildDocument $document): JsonResponse
    {
        $this->authorize('update', $child);
        $document->delete();
        return response()->json(['message' => 'Document deleted.']);
    }
}
