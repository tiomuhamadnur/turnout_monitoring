<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStationRequest;
use App\Http\Requests\UpdateStationRequest;
use App\Http\Resources\StationResource;
use App\Models\Station;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('stations.view');

        $stations = Station::query()
            ->withCount(['nodes', 'turnouts'])
            ->when($request->string('q')->toString(), function ($q, $term) {
                $q->where(fn ($w) => $w->where('code', 'like', "%{$term}%")
                                       ->orWhere('name', 'like', "%{$term}%"));
            })
            ->orderBy('code')
            ->paginate($request->integer('per_page', 25));

        return StationResource::collection($stations);
    }

    public function show(Station $station): StationResource
    {
        $this->authorize('stations.view');

        return new StationResource($station->loadCount(['nodes', 'turnouts']));
    }

    public function store(StoreStationRequest $request): StationResource
    {
        $station = Station::create($request->validated());

        return new StationResource($station);
    }

    public function update(UpdateStationRequest $request, Station $station): StationResource
    {
        $station->update($request->validated());

        return new StationResource($station);
    }

    public function destroy(Station $station): JsonResponse
    {
        $this->authorize('stations.manage');

        $station->delete();

        return response()->json(['status' => 'ok']);
    }
}
