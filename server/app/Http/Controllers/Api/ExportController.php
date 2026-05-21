<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExportService;
use Illuminate\Http\Request;

/**
 * Export endpoints. Each is a one-liner because every interesting bit
 * lives in ExportService — so any new "export X" entity can re-use the
 * same filter-parity + XLSX/PDF writer code.
 *
 * Query parameters mirror the historian/list endpoints so the SPA can
 * just hand its current filter object straight through.
 */
class ExportController extends Controller
{
    public function __construct(private readonly ExportService $exports) {}

    public function turnoutEventsExcel(Request $request)
    {
        $this->authorize('turnouts.view');
        return $this->exports->turnoutEventsExcel($request->all());
    }

    public function turnoutEventsPdf(Request $request)
    {
        $this->authorize('turnouts.view');
        return $this->exports->turnoutEventsPdf($request->all());
    }

    public function turnoutAlarmsExcel(Request $request)
    {
        $this->authorize('alarms.view');
        return $this->exports->turnoutAlarmsExcel($request->all());
    }

    public function turnoutAlarmsPdf(Request $request)
    {
        $this->authorize('alarms.view');
        return $this->exports->turnoutAlarmsPdf($request->all());
    }

    public function deviceHealthExcel(Request $request)
    {
        $this->authorize('nodes.view');
        return $this->exports->deviceHealthExcel($request->all());
    }

    public function deviceHealthPdf(Request $request)
    {
        $this->authorize('nodes.view');
        return $this->exports->deviceHealthPdf($request->all());
    }
}
