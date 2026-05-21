@extends('exports._layout', ['title' => 'Turnout Alarms'])

@section('body')
    <table>
        <thead>
            <tr>
                <th>Status</th>
                <th>Started</th>
                <th>Ended</th>
                <th>Duration</th>
                <th>Station</th>
                <th>Turnout</th>
                <th>Node</th>
                <th>Type</th>
                <th>State</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                @php
                    $duration = ($row->started_at && $row->ended_at)
                        ? \Carbon\Carbon::parse($row->started_at)->diffInSeconds(\Carbon\Carbon::parse($row->ended_at))
                        : null;
                @endphp
                <tr>
                    <td>
                        <span class="badge {{ $row->is_active ? 'active' : 'resolved' }}">
                            {{ $row->is_active ? 'ACTIVE' : 'RESOLVED' }}
                        </span>
                    </td>
                    <td>{{ $row->started_at?->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $row->ended_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                    <td>{{ $duration !== null ? ($duration . 's') : '-' }}</td>
                    <td>{{ $row->turnout?->station?->code }}</td>
                    <td>
                        <strong>{{ $row->turnout?->code }}</strong><br>
                        <span style="color:#6b7280">{{ $row->turnout?->name }}</span>
                    </td>
                    <td>{{ $row->node?->node_id }}</td>
                    <td>{{ $row->alarm_type }}</td>
                    <td>{{ $row->state }}</td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center;color:#9ca3af">No alarms.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
