<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TurnoutAlarmResource;
use App\Models\TurnoutAlarm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TurnoutAlarmController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('alarms.view');

        $alarms = TurnoutAlarm::query()
            ->with(['turnout:id,code,name', 'node:id,node_id,name'])
            ->when(!is_null($request->query('active')), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->when($request->integer('turnout_id'), fn ($q, $id) => $q->where('turnout_id', $id))
            ->orderByDesc('started_at')
            ->paginate($request->integer('per_page', 25));

        return TurnoutAlarmResource::collection($alarms);
    }
}
