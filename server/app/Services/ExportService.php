<?php

namespace App\Services;

use App\Models\DeviceHealthLog;
use App\Models\TurnoutAlarm;
use App\Models\TurnoutEvent;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Centralises every export the SPA can ask for. Each entity has a
 * `rowsFor*` method that applies the same filters used by the
 * historian/list endpoints — that way Export and Browse are guaranteed
 * to be talking about the same data.
 *
 * Excel output streams an XLSX via PhpSpreadsheet's Xlsx writer.
 * PDF output renders a Blade view through dompdf.
 */
class ExportService
{
    /** ---------------------------------------------------------------
     |  Public entry points
     |  ---------------------------------------------------------------*/

    public function turnoutEventsExcel(array $filters): StreamedResponse
    {
        $rows = $this->rowsForTurnoutEvents($filters)->get();

        return $this->streamXlsx('turnout-events', [
            'Timestamp', 'Station', 'Turnout Code', 'Turnout Name',
            'Node', 'Previous State', 'Current State', 'Channel A', 'Channel B', 'Transition',
        ], $rows->map(fn (TurnoutEvent $e) => [
            $this->fmtTs($e->source_timestamp),
            $e->turnout?->station?->code,
            $e->turnout?->code,
            $e->turnout?->name,
            $e->node?->node_id,
            $e->previous_state,
            $e->state,
            $e->channel_a ? 'Y' : 'N',
            $e->channel_b ? 'Y' : 'N',
            $e->is_transition ? 'Y' : 'N',
        ]));
    }

    public function turnoutEventsPdf(array $filters)
    {
        $rows = $this->rowsForTurnoutEvents($filters)->limit(2000)->get();

        return Pdf::loadView('exports.turnout-events', [
            'rows' => $rows,
            'filters' => $filters,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape')
          ->download($this->fileName('turnout-events', 'pdf'));
    }

    public function turnoutAlarmsExcel(array $filters): StreamedResponse
    {
        $rows = $this->rowsForTurnoutAlarms($filters)->get();

        return $this->streamXlsx('turnout-alarms', [
            'Status', 'Started', 'Ended', 'Duration (s)',
            'Station', 'Turnout Code', 'Turnout Name', 'Node', 'Type', 'State',
        ], $rows->map(fn (TurnoutAlarm $a) => [
            $a->is_active ? 'ACTIVE' : 'RESOLVED',
            $this->fmtTs($a->started_at),
            $a->ended_at ? $this->fmtTs($a->ended_at) : '',
            $a->started_at && $a->ended_at
                ? Carbon::parse($a->started_at)->diffInSeconds(Carbon::parse($a->ended_at))
                : '',
            $a->turnout?->station?->code,
            $a->turnout?->code,
            $a->turnout?->name,
            $a->node?->node_id,
            $a->alarm_type,
            $a->state,
        ]));
    }

    public function turnoutAlarmsPdf(array $filters)
    {
        $rows = $this->rowsForTurnoutAlarms($filters)->limit(2000)->get();

        return Pdf::loadView('exports.turnout-alarms', [
            'rows' => $rows,
            'filters' => $filters,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape')
          ->download($this->fileName('turnout-alarms', 'pdf'));
    }

    public function deviceHealthExcel(array $filters): StreamedResponse
    {
        $rows = $this->rowsForDeviceHealth($filters)->get();

        return $this->streamXlsx('device-health', [
            'Timestamp', 'Station', 'Node', 'CPU %', 'RAM %', 'Disk %',
            'Uptime (s)', 'MQTT',
        ], $rows->map(fn (DeviceHealthLog $h) => [
            $this->fmtTs($h->source_timestamp),
            $h->node?->station?->code,
            $h->node?->node_id,
            $h->cpu_usage,
            $h->ram_usage,
            $h->disk_usage,
            $h->uptime_seconds,
            $h->mqtt_status,
        ]));
    }

    public function deviceHealthPdf(array $filters)
    {
        $rows = $this->rowsForDeviceHealth($filters)->limit(2000)->get();

        return Pdf::loadView('exports.device-health', [
            'rows' => $rows,
            'filters' => $filters,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape')
          ->download($this->fileName('device-health', 'pdf'));
    }

    /** ---------------------------------------------------------------
     |  Query builders (filter parity with index endpoints)
     |  ---------------------------------------------------------------*/

    private function rowsForTurnoutEvents(array $filters): Builder
    {
        return TurnoutEvent::query()
            ->with(['turnout:id,code,name,station_id', 'turnout.station:id,code,name', 'node:id,node_id'])
            ->when($filters['turnout_id'] ?? null, fn ($q, $id) => $q->where('turnout_id', $id))
            ->when($filters['station_id'] ?? null, fn ($q, $id) => $q->whereHas('turnout', fn ($t) => $t->where('station_id', $id)))
            ->when($filters['state']      ?? null, fn ($q, $s)  => $q->where('state', $s))
            ->when(($filters['transitions_only'] ?? false) === true || ($filters['transitions_only'] ?? null) === '1', fn ($q) => $q->where('is_transition', true))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('source_timestamp', '>=', $d))
            ->when($filters['to']   ?? null, fn ($q, $d) => $q->where('source_timestamp', '<=', $d))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->whereHas('turnout', fn ($t) => $t->where('code', 'like', "%{$search}%")
                                                    ->orWhere('name', 'like', "%{$search}%"));
            })
            ->orderByDesc('source_timestamp');
    }

    private function rowsForTurnoutAlarms(array $filters): Builder
    {
        return TurnoutAlarm::query()
            ->with(['turnout:id,code,name,station_id', 'turnout.station:id,code,name', 'node:id,node_id'])
            ->when(array_key_exists('active', $filters) && $filters['active'] !== '' && $filters['active'] !== null,
                fn ($q) => $q->where('is_active', filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN)))
            ->when($filters['turnout_id'] ?? null, fn ($q, $id) => $q->where('turnout_id', $id))
            ->when($filters['station_id'] ?? null, fn ($q, $id) => $q->whereHas('turnout', fn ($t) => $t->where('station_id', $id)))
            ->when($filters['alarm_type'] ?? null, fn ($q, $t)  => $q->where('alarm_type', $t))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('started_at', '>=', $d))
            ->when($filters['to']   ?? null, fn ($q, $d) => $q->where('started_at', '<=', $d))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->whereHas('turnout', fn ($t) => $t->where('code', 'like', "%{$search}%")
                                                    ->orWhere('name', 'like', "%{$search}%"));
            })
            ->orderByDesc('started_at');
    }

    private function rowsForDeviceHealth(array $filters): Builder
    {
        return DeviceHealthLog::query()
            ->with('node:id,node_id,name,station_id', 'node.station:id,code,name')
            ->when($filters['node_id']    ?? null, fn ($q, $id) => $q->where('node_id', $id))
            ->when($filters['station_id'] ?? null, fn ($q, $id) => $q->whereHas('node', fn ($n) => $n->where('station_id', $id)))
            ->when($filters['mqtt_status'] ?? null, fn ($q, $s) => $q->where('mqtt_status', $s))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('source_timestamp', '>=', $d))
            ->when($filters['to']   ?? null, fn ($q, $d) => $q->where('source_timestamp', '<=', $d))
            ->orderByDesc('source_timestamp');
    }

    /** ---------------------------------------------------------------
     |  Writers
     |  ---------------------------------------------------------------*/

    private function streamXlsx(string $sheetSlug, array $headers, iterable $rows): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr(ucfirst(str_replace('-', ' ', $sheetSlug)), 0, 31));

        // Header row, bolded.
        foreach ($headers as $col => $label) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $label);
        }
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')
              ->getFont()->setBold(true);

        $rowIndex = 2;
        foreach ($rows as $row) {
            $colIndex = 1;
            foreach ($row as $cell) {
                $sheet->setCellValueByColumnAndRow($colIndex++, $rowIndex, $cell);
            }
            $rowIndex++;
        }

        foreach (range(1, count($headers)) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        $filename = $this->fileName($sheetSlug, 'xlsx');

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function fileName(string $slug, string $ext): string
    {
        return $slug . '_' . now()->format('Ymd_His') . '.' . $ext;
    }

    private function fmtTs($ts): string
    {
        if (!$ts) return '';
        return Carbon::parse($ts)->format('Y-m-d H:i:s');
    }
}
