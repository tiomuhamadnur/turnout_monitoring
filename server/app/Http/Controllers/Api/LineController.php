<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLineRequest;
use App\Http\Requests\UpdateLineRequest;
use App\Http\Resources\LineResource;
use App\Models\Line;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LineController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('lines.view');

        $lines = Line::query()
            ->withCount('turnouts')
            ->when($request->string('q')->toString(), function ($q, $term) {
                $q->where(fn ($w) => $w->where('code', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%"));
            })
            ->orderBy('code')
            ->paginate($request->integer('per_page', 25));

        return LineResource::collection($lines);
    }

    public function show(Line $line): LineResource
    {
        $this->authorize('lines.view');

        return new LineResource($line->loadCount('turnouts'));
    }

    public function store(StoreLineRequest $request): LineResource
    {
        $line = Line::create($request->validated());

        return new LineResource($line);
    }

    public function update(UpdateLineRequest $request, Line $line): LineResource
    {
        $line->update($request->validated());

        return new LineResource($line);
    }

    public function destroy(Line $line): JsonResponse
    {
        $this->authorize('lines.manage');

        $line->delete();

        return response()->json(['status' => 'ok']);
    }
}
