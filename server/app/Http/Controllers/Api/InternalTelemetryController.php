<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IngestDeviceHealthRequest;
use App\Http\Requests\IngestHeartbeatRequest;
use App\Http\Requests\IngestTurnoutStateRequest;
use App\Http\Resources\DeviceHealthLogResource;
use App\Http\Resources\NodeResource;
use App\Http\Resources\TurnoutStateResource;
use App\Services\TelemetryIngestService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InternalTelemetryController extends Controller
{
    public function __construct(private readonly TelemetryIngestService $telemetry) {}

    public function ingestState(IngestTurnoutStateRequest $request): TurnoutStateResource
    {
        $this->authorizeIngest($request);

        return new TurnoutStateResource(
            $this->telemetry->ingestState($request->validated())
        );
    }

    public function ingestHeartbeat(IngestHeartbeatRequest $request): NodeResource
    {
        $this->authorizeIngest($request);

        return new NodeResource(
            $this->telemetry->ingestHeartbeat($request->validated())
        );
    }

    public function ingestHealth(IngestDeviceHealthRequest $request): DeviceHealthLogResource
    {
        $this->authorizeIngest($request);

        return new DeviceHealthLogResource(
            $this->telemetry->ingestHealth($request->validated())
        );
    }

    private function authorizeIngest(Request $request): void
    {
        $configured = (string) config('telemetry.ingest_token');
        $provided = (string) $request->header('X-Ingest-Token');

        abort_if($configured === '', Response::HTTP_SERVICE_UNAVAILABLE, 'Telemetry ingest token is not configured.');
        abort_unless(hash_equals($configured, $provided), Response::HTTP_UNAUTHORIZED, 'Invalid ingest token.');
    }
}
