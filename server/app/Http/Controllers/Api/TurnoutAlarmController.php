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
            ->with(['turnout:id,code,name,station_id', 'turnout.station:id,code,name', 'node:id,node_id,name'])
            ->when(!is_null($request->query('active')),     fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->when($request->integer('turnout_id'),         fn ($q, $id) => $q->where('turnout_id', $id))
            ->when($request->integer('node_id'),            fn ($q, $id) => $q->where('node_id', $id))
            ->when($request->string('alarm_type')->toString(), fn ($q, $t) => $q->where('alarm_type', $t))
            ->when($request->integer('station_id'), function ($q, $stationId) {
                $q->whereHas('turnout', fn ($t) => $t->where('station_id', $stationId));
            })
            ->when($request->date('from'), fn ($q, $d) => $q->where('started_at', '>=', $d))
            ->when($request->date('to'),   fn ($q, $d) => $q->where('started_at', '<=', $d))
            ->when($request->string('search')->toString(), function ($q, $search) {
                $q->whereHas('turnout', function ($t) use ($search) {
                    $t->where('code', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('started_at')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return TurnoutAlarmResource::collection($alarms);
    }
}
