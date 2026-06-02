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
        $specialists = Specialist::when($search, function ($query, $search) {
            $query->where(function ($query) use ($search) {
                $query->where('display_name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('specialty', 'like', "%{$search}%");
            });
        })
            ->latest()->paginate(PAGINATION_COUNT);

        return view('admin.specialists.index', compact('specialists', 'search'));
    }

    public function create()
    {
        return view('admin.specialists.create');
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        Specialist::create($data);

        return redirect()->route('admin.specialists.index')->with('success', 'Specialist created.');
    }

    public function edit(Specialist $specialist)
    {
        return view('admin.specialists.edit', compact('specialist'));
    }

    public function update(Request $request, Specialist $specialist)
    {
        $data = $this->validatedData($request);

        if ($request->hasFile('avatar') && $specialist->avatar) {
            Storage::disk('public')->delete($specialist->avatar);
        }

        $specialist->update($data);

        return redirect()->route('admin.specialists.index')->with('success', 'Specialist updated.');
    }

    public function destroy(Specialist $specialist)
    {
        $specialist->delete();
        return back()->with('success', 'Specialist deleted.');
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'name'             => 'required|string|max:150',
            'title'            => 'nullable|string|max:150',
            'bio'              => 'nullable|string',
            'session_fee'      => 'nullable|numeric|min:0',
            'currency'         => 'nullable|string|max:10',
            'is_active'        => 'boolean',
            'is_available'     => 'boolean',
            'avatar'           => 'nullable|image|max:2048',
            'specializations'  => 'nullable|string',
            'languages'        => 'nullable|string',
        ]);

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('specialists', 'public');
        }

        $data['specializations'] = $this->parseList($data['specializations'] ?? '');
        $data['languages'] = $this->parseList($data['languages'] ?? '');
        $data['is_active'] = $request->boolean('is_active');
        $data['is_available'] = $request->boolean('is_available');

        return $data;
    }

    private function parseList(string $value): array
    {
        return array_values(array_filter(array_map(
            static fn(string $item) => trim($item),
            explode(',', $value)
        )));
    }
}
