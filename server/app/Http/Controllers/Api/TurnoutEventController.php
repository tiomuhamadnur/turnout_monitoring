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
            ->with(['turnout:id,code,name,station_id', 'turnout.station:id,code,name', 'node:id,node_id,name'])
            ->when($request->integer('turnout_id'), fn ($q, $id) => $q->where('turnout_id', $id))
            ->when($request->integer('node_id'),    fn ($q, $id) => $q->where('node_id', $id))
            ->when($request->integer('station_id'), function ($q, $stationId) {
                $q->whereHas('turnout', fn ($t) => $t->where('station_id', $stationId));
            })
            ->when($request->string('state')->toString(), fn ($q, $state) => $q->where('state', $state))
            ->when($request->boolean('transitions_only'), fn ($q) => $q->where('is_transition', true))
            ->when($request->date('from'), fn ($q, $d) => $q->where('source_timestamp', '>=', $d))
            ->when($request->date('to'),   fn ($q, $d) => $q->where('source_timestamp', '<=', $d))
            ->when($request->string('search')->toString(), function ($q, $search) {
                $q->whereHas('turnout', function ($t) use ($search) {
                    $t->where('code', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('source_timestamp')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return TurnoutEventResource::collection($events);
    }
}
