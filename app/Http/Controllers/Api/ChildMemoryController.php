<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreChildMemoryRequest;
use App\Http\Requests\Api\UpdateChildMemoryRequest;
use App\Http\Resources\ChildMemoryResource;
use App\Models\Child;
use App\Models\ChildMemory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChildMemoryController extends Controller
{
    public function index(Request $request, Child $child): JsonResponse
    {
        $this->authorize('view', $child);
        $memories = $child->memories()->latest()->get();
        return response()->json(ChildMemoryResource::collection($memories));
    }

    public function store(StoreChildMemoryRequest $request): JsonResponse
    {
        $data  = $request->validated();
        $child = $request->user()->children()->findOrFail($data['child_id']);

        $memory = ChildMemory::create([
            'child_id'   => $child->id,
            'key'        => $data['key'],
            'content'    => $data['content'],
            'confidence' => 1.0,
        ]);

        return response()->json(new ChildMemoryResource($memory), 201);
    }

    public function update(UpdateChildMemoryRequest $request, ChildMemory $memory): JsonResponse
    {
        $this->authorize('update', $memory);
        $memory->update($request->validated());
        return response()->json(new ChildMemoryResource($memory->fresh()));
    }

    public function destroy(Request $request, ChildMemory $memory): JsonResponse
    {
        $this->authorize('delete', $memory);
        $memory->delete();
        return response()->json(['message' => 'Memory deleted.']);
    }
}
