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
            ->with('node:id,node_id,name')
            ->when($request->integer('node_id'), fn ($q, $id) => $q->where('node_id', $id))
            ->orderByDesc('source_timestamp')
            ->paginate($request->integer('per_page', 25));

        return DeviceHealthLogResource::collection($logs);
    }
}
