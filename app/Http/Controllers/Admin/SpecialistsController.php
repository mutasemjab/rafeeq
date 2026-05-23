<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Specialist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SpecialistsController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $specialists = Specialist::when($search, fn($q) => $q->where('name', 'like', "%{$search}%")
            ->orWhere('title', 'like', "%{$search}%"))
            ->latest()->paginate(PAGINATION_COUNT);
        return view('admin.specialists.index', compact('specialists', 'search'));
    }

    public function create()
    {
        return view('admin.specialists.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:150',
            'title'         => 'nullable|string|max:150',
            'bio'           => 'nullable|string',
            'session_fee'   => 'nullable|numeric|min:0',
            'currency'      => 'nullable|string|max:10',
            'is_active'     => 'boolean',
            'is_available'  => 'boolean',
            'avatar'        => 'nullable|image|max:2048',
            'specializations' => 'nullable|string',
            'languages'     => 'nullable|string',
        ]);
        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('specialists', 'public');
        }
        $data['specializations'] = json_encode(array_filter(explode(',', $data['specializations'] ?? '')));
        $data['languages']       = json_encode(array_filter(explode(',', $data['languages'] ?? '')));
        $data['is_active']       = $request->boolean('is_active');
        $data['is_available']    = $request->boolean('is_available');
        Specialist::create($data);
        return redirect()->route('admin.specialists.index')->with('success', 'Specialist created.');
    }

    public function edit(Specialist $specialist)
    {
        return view('admin.specialists.edit', compact('specialist'));
    }

    public function update(Request $request, Specialist $specialist)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:150',
            'title'         => 'nullable|string|max:150',
            'bio'           => 'nullable|string',
            'session_fee'   => 'nullable|numeric|min:0',
            'currency'      => 'nullable|string|max:10',
            'is_active'     => 'boolean',
            'is_available'  => 'boolean',
            'avatar'        => 'nullable|image|max:2048',
            'specializations' => 'nullable|string',
            'languages'     => 'nullable|string',
        ]);
        if ($request->hasFile('avatar')) {
            if ($specialist->avatar) Storage::disk('public')->delete($specialist->avatar);
            $data['avatar'] = $request->file('avatar')->store('specialists', 'public');
        }
        $data['specializations'] = json_encode(array_filter(explode(',', $data['specializations'] ?? '')));
        $data['languages']       = json_encode(array_filter(explode(',', $data['languages'] ?? '')));
        $data['is_active']       = $request->boolean('is_active');
        $data['is_available']    = $request->boolean('is_available');
        $specialist->update($data);
        return redirect()->route('admin.specialists.index')->with('success', 'Specialist updated.');
    }

    public function destroy(Specialist $specialist)
    {
        $specialist->delete();
        return back()->with('success', 'Specialist deleted.');
    }
}
