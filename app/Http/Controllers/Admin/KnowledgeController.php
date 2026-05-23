<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessKnowledgeDocumentJob;
use App\Models\KnowledgeDocument;
use Illuminate\Http\Request;

class KnowledgeController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $docs = KnowledgeDocument::withTrashed()
            ->when($search, fn($q) => $q->where('title', 'like', "%{$search}%"))
            ->latest()->paginate(PAGINATION_COUNT);
        return view('admin.knowledge.index', compact('docs', 'search'));
    }

    public function create()
    {
        return view('admin.knowledge.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'file'     => 'required|file|mimes:pdf,docx,doc,txt|max:51200',
            'title'    => 'nullable|string|max:255',
            'category' => 'nullable|string|max:100',
        ]);
        $file = $request->file('file');
        $path = $file->store('knowledge', 'public');
        $doc = KnowledgeDocument::create([
            'title'         => $request->input('title', $file->getClientOriginalName()),
            'category'      => $request->input('category'),
            'file_path'     => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getMimeType(),
            'file_size'     => $file->getSize(),
            'status'        => 'pending',
            'uploaded_by'   => auth('admin')->id(),
        ]);
        ProcessKnowledgeDocumentJob::dispatch($doc->id);
        return redirect()->route('admin.knowledge.index')->with('success', 'Document uploaded and queued for processing.');
    }

    public function destroy(KnowledgeDocument $knowledge)
    {
        $knowledge->delete();
        return back()->with('success', 'Document deleted.');
    }

    public function reprocess(KnowledgeDocument $knowledge)
    {
        $knowledge->update(['status' => 'pending']);
        ProcessKnowledgeDocumentJob::dispatch($knowledge->id);
        return back()->with('success', 'Document queued for reprocessing.');
    }
}
