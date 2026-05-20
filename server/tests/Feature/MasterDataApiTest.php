<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Line;
use App\Models\User;
use Database\Seeders\LineSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MasterDataApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('telemetry.ingest_token', 'test-ingest-token');
        $this->seed(RolePermissionSeeder::class);
        $this->seed(LineSeeder::class);
    }

    public function test_admin_can_manage_phase_two_master_data(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);
        $line = Line::query()->where('code', 'UT')->firstOrFail();

        $stationResponse = $this->postJson('/api/stations', [
            'code' => 'LBB',
            'name' => 'Lebak Bulus',
        ])->assertCreated();

        $stationId = $stationResponse->json('data.id');

        $this->postJson('/api/nodes', [
            'station_id' => $stationId,
            'node_id' => 'LBB-NODE-01',
            'name' => 'LBB Main Node',
            'status' => 'online',
            'metadata' => ['ups' => true],
        ])->assertCreated();

        $turnoutResponse = $this->postJson('/api/turnouts', [
            'station_id' => $stationId,
            'code' => 'W1110',
            'name' => 'Turnout W1110',
            'type' => '1:10',
            'direction' => 'Right',
            'line_id' => $line->id,
            'chainage' => 12345.5,
        ])->assertCreated();

        $turnoutId = $turnoutResponse->json('data.id');

        $this->post('/api/turnouts/'.$turnoutId.'/photo', [
            'photo' => UploadedFile::fake()->image('turnout.jpg'),
        ])->assertOk();

        $this->assertCount(1, Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('stations', 1);
        $this->assertDatabaseCount('lines', 3);
        $this->assertDatabaseCount('nodes', 1);
        $this->assertDatabaseCount('turnouts', 1);
        $this->assertDatabaseHas('turnouts', ['code' => 'W1110', 'direction' => 'Right']);
        $this->assertGreaterThanOrEqual(3, AuditLog::count());
        $this->getJson('/api/turnouts/'.$turnoutId)
            ->assertOk()
            ->assertJsonPath('data.photo_url', url('/api/turnouts/'.$turnoutId.'/photo'));
        $this->get('/api/turnouts/'.$turnoutId.'/photo')->assertOk();
    }

    public function test_audit_log_index_returns_station_entries(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $station = $this->postJson('/api/stations', [
            'code' => 'BLM',
            'name' => 'Blok M',
        ])->assertCreated()->json('data');

        $this->getJson('/api/audit-logs?auditable_type=Station')
            ->assertOk()
            ->assertJsonPath('data.0.auditable_type', 'Station')
            ->assertJsonPath('data.0.action', 'created');

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => 'App\Models\Station',
            'auditable_id' => $station['id'],
        ]);
    }

    public function test_viewer_cannot_create_station(): void
    {
        $user = User::factory()->create();
        $user->assignRole('viewer');

        $this->actingAs($user)
            ->postJson('/api/stations', [
                'code' => 'BHI',
                'name' => 'Bundaran HI',
            ])
            ->assertForbidden();
    }

    public function test_line_seeder_provides_default_track_master_data(): void
    {
        $this->assertDatabaseHas('lines', ['code' => 'UT', 'name' => 'Up Track']);
        $this->assertDatabaseHas('lines', ['code' => 'DT', 'name' => 'Down Track']);
        $this->assertDatabaseHas('lines', ['code' => 'MT', 'name' => 'Middle Track']);
    }

    public function test_internal_telemetry_ingest_creates_runtime_records(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $line = Line::query()->where('code', 'UT')->firstOrFail();

        $stationId = $this->actingAs($user)
            ->postJson('/api/stations', ['code' => 'BHI', 'name' => 'Bundaran HI'])
            ->assertCreated()
            ->json('data.id');

        $this->postJson('/api/nodes', [
            'station_id' => $stationId,
            'node_id' => 'BHI-NODE-01',
            'name' => 'BHI Node',
            'status' => 'unknown',
        ])->assertCreated();

        $turnout = $this->postJson('/api/turnouts', [
            'station_id' => $stationId,
            'code' => 'W2201',
            'name' => 'Turnout W2201',
            'type' => '1:8',
            'direction' => 'Left',
            'line_id' => $line->id,
            'chainage' => 204.2,
        ])->assertCreated()->json('data');

        $headers = ['X-Ingest-Token' => 'test-ingest-token'];

        $this->postJson('/api/internal/telemetry/state', [
            'timestamp' => '2026-05-21T10:00:00+07:00',
            'turnout_uuid' => $turnout['uuid'],
            'turnout_code' => 'W2201',
            'state' => 'FAILURE',
            'channel_a' => false,
            'channel_b' => false,
            'node_id' => 'BHI-NODE-01',
        ], $headers)->assertOk();

        $this->postJson('/api/internal/telemetry/heartbeat', [
            'timestamp' => '2026-05-21T10:00:05+07:00',
            'node_id' => 'BHI-NODE-01',
            'mqtt_status' => 'connected',
            'status' => 'online',
        ], $headers)->assertOk();

        $this->postJson('/api/internal/telemetry/health', [
            'timestamp' => '2026-05-21T10:00:10+07:00',
            'node_id' => 'BHI-NODE-01',
            'cpu_usage' => 12.5,
            'ram_usage' => 44.1,
            'disk_usage' => 70.0,
            'uptime_seconds' => 3600,
            'mqtt_status' => 'connected',
            'container_health' => ['app' => 'healthy'],
        ], $headers)->assertCreated();

        $this->assertDatabaseHas('turnout_states', ['state' => 'FAILURE']);
        $this->assertDatabaseHas('turnout_events', ['state' => 'FAILURE', 'event_type' => 'state']);
        $this->assertDatabaseHas('turnout_alarms', ['state' => 'FAILURE', 'is_active' => true]);
        $this->assertDatabaseHas('device_health_logs', ['mqtt_status' => 'connected']);
        $this->assertDatabaseHas('nodes', ['node_id' => 'BHI-NODE-01', 'status' => 'online', 'mqtt_status' => 'connected']);
    }
}
