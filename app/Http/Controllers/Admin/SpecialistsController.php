<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Specialist;
use App\Models\SpecialistAvailability;
use Carbon\Carbon;
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
            ->latest()->paginate($this->paginationCount());

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
        $specialist->load([
            'availabilities' => fn($query) => $query
                ->orderBy('available_date')
                ->orderBy('start_time'),
        ]);

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

    public function storeAvailability(Request $request, Specialist $specialist)
    {
        $data = $request->validateWithBag('availability', [
            'available_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'slot_duration_minutes' => 'required|integer|min:1|max:1440',
            'capacity' => 'required|integer|min:1|max:1000',
            'slot_is_available' => 'boolean',
        ]);

        $data['start_time'] = Carbon::createFromFormat('H:i', $data['start_time'])->format('H:i:s');
        $data['end_time'] = Carbon::createFromFormat('H:i', $data['end_time'])->format('H:i:s');
        $data['is_available'] = $request->boolean('slot_is_available');
        unset($data['slot_is_available']);

        $specialist->availabilities()->create($data);

        return redirect()
            ->route('admin.specialists.edit', $specialist)
            ->with('success', 'Availability slot added.');
    }

    public function destroyAvailability(Specialist $specialist, SpecialistAvailability $availability)
    {
        abort_unless($availability->specialist_id === $specialist->id, 404);

        $availability->delete();

        return redirect()
            ->route('admin.specialists.edit', $specialist)
            ->with('success', 'Availability slot deleted.');
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
