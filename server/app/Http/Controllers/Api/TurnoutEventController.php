<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TurnoutEventResource;
use App\Models\TurnoutEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TurnoutEventController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('turnouts.view');

        $events = TurnoutEvent::query()
            ->with(['turnout:id,code,name', 'node:id,node_id,name'])
            ->when($request->integer('turnout_id'), fn ($q, $id) => $q->where('turnout_id', $id))
            ->when($request->string('state')->toString(), fn ($q, $state) => $q->where('state', $state))
            ->orderByDesc('source_timestamp')
            ->paginate($request->integer('per_page', 25));

        return TurnoutEventResource::collection($events);
    }
}
