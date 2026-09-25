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
            'user_id'    => $request->user()->id,
            'memory_key' => $data['key'],
            'type'       => $data['type'] ?? 'general',
            'title'      => $data['title'] ?? $data['key'],
            'content'    => $data['content'],
            'confidence' => 1.0,
            'status'     => 'active',
            'source'     => 'caregiver_manual',
            'last_confirmed_at' => now(),
        ]);

        return response()->json(new ChildMemoryResource($memory), 201);
    }

    public function update(UpdateChildMemoryRequest $request, ChildMemory $memory): JsonResponse
    {
        $this->authorize('update', $memory);
        $data = $request->validated();
        if (array_key_exists('key', $data)) {
            $data['memory_key'] = $data['key'];
            unset($data['key']);
        }
        $data['last_confirmed_at'] = now();
        $memory->update($data);
        return response()->json(new ChildMemoryResource($memory->fresh()));
    }

    public function destroy(Request $request, ChildMemory $memory): JsonResponse
    {
        $this->authorize('delete', $memory);
        $memory->delete();
        return response()->json(['message' => 'Memory deleted.']);
    }
}
