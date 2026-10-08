<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UploadChildDocumentRequest;
use App\Http\Resources\PersonDocumentResource;
use App\Jobs\ProcessPersonDocumentJob;
use App\Models\PersonDocument;
use App\Models\PersonProfile;
use App\Services\Documents\PrivateDocumentStorage;
use App\Services\Documents\PrivateFileProcessingDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PersonDocumentController extends Controller
{
    public function index(Request $request, PersonProfile $personProfile)
    {
        $this->owner($request, $personProfile);
        return response()->json(PersonDocumentResource::collection($personProfile->documents()->where('user_id', $request->user()->id)->latest()->get()));
    }

    public function store(UploadChildDocumentRequest $request, PersonProfile $personProfile)
    {
        $this->owner($request, $personProfile);
        $file = $request->file('file');
        $path = $file->store('people/'.$personProfile->id.'/documents', 'private');
        $doc = $personProfile->documents()->create([
            'user_id' => $request->user()->id, 'original_name' => $file->getClientOriginalName(),
            'title' => $request->input('title') ?: $file->getClientOriginalName(), 'file_path' => $path,
            'mime_type' => $file->getMimeType(), 'file_size' => $file->getSize(), 'storage_disk' => 'private',
            'category' => $request->input('document_type') ?: 'other', 'status' => 'uploaded',
        ]);
        if ($doc->canProcess()) {
            app(PrivateFileProcessingDispatcher::class)->dispatch(ProcessPersonDocumentJob::class, (int) $doc->id);
        }
        return response()->json(new PersonDocumentResource($doc), 201);
    }

    public function retry(Request $request, PersonProfile $personProfile, PersonDocument $document)
    {
        $this->documentOwner($request, $personProfile, $document);
        abort_unless($document->canProcess(), 403, 'AI consent is required for document processing.');
        abort_unless(PersonDocument::whereKey($document->id)->where('status', 'failed')->update([
            'status' => 'uploaded', 'processing_error' => null, 'processed_at' => null,
        ]), 409, 'Only failed documents can be retried.');
        app(PrivateFileProcessingDispatcher::class)->dispatch(ProcessPersonDocumentJob::class, (int) $document->id);
        return response()->json(new PersonDocumentResource($document->fresh()));
    }

    public function destroy(Request $request, PersonProfile $personProfile, PersonDocument $document)
    {
        $this->documentOwner($request, $personProfile, $document);
        DB::transaction(function () use ($document): void {
            $locked = PersonDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            app(PrivateDocumentStorage::class)->delete($locked);
            $locked->forceDelete();
        });
        return response()->json(['message' => 'Document deleted.']);
    }

    public function downloadSigned(Request $request, PersonDocument $document)
    {
        abort_unless($request->hasValidSignature() && (int) $request->query('owner') === (int) $document->user_id, 403);
        abort_unless($document->personProfile()->where('user_id', $document->user_id)->exists(), 404);
        return app(PrivateDocumentStorage::class)->download($document);
    }

    private function owner(Request $request, PersonProfile $person): void
    {
        abort_unless((int) $person->user_id === (int) $request->user()->id, 403);
    }

    private function documentOwner(Request $request, PersonProfile $person, PersonDocument $document): void
    {
        $this->owner($request, $person);
        abort_unless((int) $document->person_profile_id === (int) $person->id && (int) $document->user_id === (int) $request->user()->id, 404);
    }
}
