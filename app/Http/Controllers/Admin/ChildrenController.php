<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\User;
use Illuminate\Http\Request;

class ChildrenController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $children = Child::withTrashed()
            ->with('user')
            ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"))
            ->latest()->paginate($this->paginationCount());
        return view('admin.children.index', compact('children', 'search'));
    }

    public function show(Child $child)
    {
        $child->load('user', 'documents', 'memories');
        return view('admin.children.show', compact('child'));
    }

    public function destroy(Child $child)
    {
        $child->delete();
        return back()->with('success', 'Child deleted.');
    }

    public function restore($id)
    {
        Child::withTrashed()->findOrFail($id)->restore();
        return back()->with('success', 'Child restored.');
    }
}
