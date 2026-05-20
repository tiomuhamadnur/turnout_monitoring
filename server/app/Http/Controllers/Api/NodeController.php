<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNodeRequest;
use App\Http\Requests\UpdateNodeRequest;
use App\Http\Resources\NodeResource;
use App\Models\Node;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NodeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('nodes.view');

        $nodes = Node::query()
            ->with('station:id,code,name')
            ->when($request->integer('station_id'), fn ($q, $sid) => $q->where('station_id', $sid))
            ->when($request->string('q')->toString(), function ($q, $term) {
                $q->where(fn ($w) => $w->where('node_id', 'like', "%{$term}%")
                                       ->orWhere('name',  'like', "%{$term}%"));
            })
            ->orderBy('node_id')
            ->paginate($request->integer('per_page', 25));

        return NodeResource::collection($nodes);
    }

    public function show(Node $node): NodeResource
    {
        $this->authorize('nodes.view');

        return new NodeResource($node->load('station:id,code,name'));
    }

    public function store(StoreNodeRequest $request): NodeResource
    {
        $node = Node::create($request->validated());

        return new NodeResource($node->load('station:id,code,name'));
    }

    public function update(UpdateNodeRequest $request, Node $node): NodeResource
    {
        $node->update($request->validated());

        return new NodeResource($node->load('station:id,code,name'));
    }

    public function destroy(Node $node): JsonResponse
    {
        $this->authorize('nodes.manage');

        $node->delete();

        return response()->json(['status' => 'ok']);
    }
}
