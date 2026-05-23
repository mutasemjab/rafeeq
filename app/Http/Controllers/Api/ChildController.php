<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreChildRequest;
use App\Http\Requests\Api\UpdateChildRequest;
use App\Http\Resources\ChildResource;
use App\Models\Child;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ChildController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $children = $request->user()->children()->latest()->get();
        return response()->json(ChildResource::collection($children));
    }

    public function store(StoreChildRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('children/avatars', 'public');
        }

        $data['user_id'] = $request->user()->id;
        $child = Child::create($data);

        return response()->json(new ChildResource($child), 201);
    }

    public function show(Request $request, Child $child): JsonResponse
    {
        $this->authorize('view', $child);
        return response()->json(new ChildResource($child));
    }

    public function update(UpdateChildRequest $request, Child $child): JsonResponse
    {
        $this->authorize('update', $child);
        $data = $request->validated();

        if ($request->hasFile('avatar')) {
            if ($child->avatar) Storage::disk('public')->delete($child->avatar);
            $data['avatar'] = $request->file('avatar')->store('children/avatars', 'public');
        }

        $child->update($data);
        return response()->json(new ChildResource($child->fresh()));
    }

    public function destroy(Request $request, Child $child): JsonResponse
    {
        $this->authorize('delete', $child);
        $child->delete();
        return response()->json(['message' => 'Child deleted.']);
    }
}
