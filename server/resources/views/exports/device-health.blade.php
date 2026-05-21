@extends('exports._layout', ['title' => 'Device Health Logs'])

@section('body')
    <table>
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>Station</th>
                <th>Node</th>
                <th>CPU %</th>
                <th>RAM %</th>
                <th>Disk %</th>
                <th>Uptime (s)</th>
                <th>MQTT</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row->source_timestamp?->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $row->node?->station?->code }}</td>
                    <td>{{ $row->node?->node_id }}</td>
                    <td>{{ $row->cpu_usage ?? '-' }}</td>
                    <td>{{ $row->ram_usage ?? '-' }}</td>
                    <td>{{ $row->disk_usage ?? '-' }}</td>
                    <td>{{ $row->uptime_seconds ?? '-' }}</td>
                    <td>{{ $row->mqtt_status }}</td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center;color:#9ca3af">No logs.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
