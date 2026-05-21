<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeviceHealthLogResource;
use App\Models\DeviceHealthLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeviceHealthLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('nodes.view');

        $logs = DeviceHealthLog::query()
            ->with('node:id,node_id,name,station_id', 'node.station:id,code,name')
            ->when($request->integer('node_id'), fn ($q, $id) => $q->where('node_id', $id))
            ->when($request->integer('station_id'), function ($q, $stationId) {
                $q->whereHas('node', fn ($n) => $n->where('station_id', $stationId));
            })
            ->when($request->string('mqtt_status')->toString(), fn ($q, $s) => $q->where('mqtt_status', $s))
            ->when($request->date('from'), fn ($q, $d) => $q->where('source_timestamp', '>=', $d))
            ->when($request->date('to'),   fn ($q, $d) => $q->where('source_timestamp', '<=', $d))
            ->orderByDesc('source_timestamp')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return DeviceHealthLogResource::collection($logs);
    }
}
