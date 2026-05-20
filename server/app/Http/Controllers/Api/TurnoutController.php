<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTurnoutRequest;
use App\Http\Requests\UpdateTurnoutRequest;
use App\Http\Resources\TurnoutResource;
use App\Models\Turnout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TurnoutController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('turnouts.view');

        $turnouts = Turnout::query()
            ->with(['station:id,code,name', 'line:id,code,name'])
            ->when($request->integer('station_id'), fn ($q, $sid) => $q->where('station_id', $sid))
            ->when($request->integer('line_id'), fn ($q, $lid) => $q->where('line_id', $lid))
            ->when($request->string('q')->toString(), function ($q, $term) {
                $q->where(fn ($w) => $w->where('code', 'like', "%{$term}%")
                                       ->orWhere('name', 'like', "%{$term}%"));
            })
            ->orderBy('code')
            ->paginate($request->integer('per_page', 25));

        return TurnoutResource::collection($turnouts);
    }

    public function show(Turnout $turnout): TurnoutResource
    {
        $this->authorize('turnouts.view');

        return new TurnoutResource($turnout->load(['station:id,code,name', 'line:id,code,name']));
    }

    public function photo(Turnout $turnout): StreamedResponse
    {
        $this->authorize('turnouts.view');

        abort_unless(
            $turnout->photo_path && Storage::disk('public')->exists($turnout->photo_path),
            404
        );

        return Storage::disk('public')->response($turnout->photo_path);
    }

    public function store(StoreTurnoutRequest $request): TurnoutResource
    {
        $turnout = Turnout::create($request->validated());

        return new TurnoutResource($turnout->load(['station:id,code,name', 'line:id,code,name']));
    }

    public function update(UpdateTurnoutRequest $request, Turnout $turnout): TurnoutResource
    {
        $turnout->update($request->validated());

        return new TurnoutResource($turnout->load(['station:id,code,name', 'line:id,code,name']));
    }

    public function destroy(Turnout $turnout): JsonResponse
    {
        $this->authorize('turnouts.manage');

        // Remove the stored photo too — orphan files in storage/public clutter
        // backups and break Storage::url() consumers downstream.
        if ($turnout->photo_path) {
            Storage::disk('public')->delete($turnout->photo_path);
        }
        $turnout->delete();

        return response()->json(['status' => 'ok']);
    }

    /**
     * POST /api/turnouts/{turnout}/photo — multipart photo upload.
     * Kept separate from store/update so those endpoints can stay JSON-only.
     */
    public function uploadPhoto(Request $request, Turnout $turnout): TurnoutResource
    {
        $this->authorize('turnouts.manage');

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        if ($turnout->photo_path) {
            Storage::disk('public')->delete($turnout->photo_path);
        }

        $path = $request->file('photo')->store("turnouts/{$turnout->uuid}", 'public');
        $turnout->update(['photo_path' => $path]);

        return new TurnoutResource($turnout->load(['station:id,code,name', 'line:id,code,name']));
    }
}
