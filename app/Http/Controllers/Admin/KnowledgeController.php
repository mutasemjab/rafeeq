<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessKnowledgeDocumentJob;
use App\Models\KnowledgeDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KnowledgeController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status');

        $docs = KnowledgeDocument::withTrashed()
            ->withCount('chunks')
            ->when($search, fn($q) => $q->where('title', 'like', "%{$search}%"))
            ->when($status && $status !== 'all', fn($q) => $q->where('status', $status))
            ->latest()
            ->paginate(PAGINATION_COUNT)
            ->withQueryString();

        $stats = [
            'total'      => KnowledgeDocument::count(),
            'uploaded'   => KnowledgeDocument::where('status', 'uploaded')->count(),
            'processing' => KnowledgeDocument::where('status', 'processing')->count(),
            'processed'  => KnowledgeDocument::where('status', 'processed')->count(),
            'failed'     => KnowledgeDocument::where('status', 'failed')->count(),
        ];

        $liveIds = $docs->getCollection()
            ->filter(fn($doc) => in_array($doc->status, KnowledgeDocument::IN_PROGRESS_STATUSES, true))
            ->pluck('id')
            ->values();

        return view('admin.knowledge.index', compact('docs', 'search', 'status', 'stats', 'liveIds'));
    }

    public function create()
    {
        return view('admin.knowledge.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'file'     => 'required|file|mimes:pdf,docx,doc,pptx,txt|max:51200',
            'title'    => 'nullable|string|max:255',
            'category' => 'nullable|string|max:100',
        ]);

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
            'uploaded_by'   => null,
        ]);

        ProcessKnowledgeDocumentJob::dispatchWithSyncFallback($doc->id);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($this->serializeDocument($doc->loadCount('chunks')), 201);
        }

        return redirect()
            ->route('admin.knowledge.index')
            ->with('success', 'Document uploaded and queued for processing.');
    }

    public function statuses(Request $request): JsonResponse
    {
        $rawIds = $request->input('ids', []);

        if (is_string($rawIds)) {
            $rawIds = explode(',', $rawIds);
        }

        $ids = collect($rawIds)
            ->map(fn($id) => (int) $id)
            ->filter()
            ->values();

        if ($ids->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $documents = KnowledgeDocument::withCount('chunks')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $data = $ids
            ->map(fn($id) => $documents->get($id))
            ->filter()
            ->map(fn(KnowledgeDocument $document) => $this->serializeDocument($document))
            ->values();

        return response()->json(['data' => $data]);
    }

    public function destroy(KnowledgeDocument $knowledge)
    {
        $knowledge->delete();
        return back()->with('success', 'Document deleted.');
    }

    public function reprocess(KnowledgeDocument $knowledge)
    {
        $knowledge->update([
            'status'           => 'uploaded',
            'processing_error' => null,
            'processed_at'     => null,
        ]);
        ProcessKnowledgeDocumentJob::dispatchWithSyncFallback($knowledge->id);
        return back()->with('success', 'Document queued for reprocessing.');
    }

    private function serializeDocument(KnowledgeDocument $document): array
    {
        return [
            'id'               => $document->id,
            'title'            => $document->title,
            'original_name'    => $document->original_name,
            'status'           => $document->status,
            'category'         => $document->category,
            'mime_type'        => $document->mime_type,
            'file_size'        => $document->file_size,
            'chunk_count'      => $document->chunks_count ?? 0,
            'processing_error' => $document->processing_error,
            'processed_at'     => $document->processed_at?->toISOString(),
        ];
    }
}
