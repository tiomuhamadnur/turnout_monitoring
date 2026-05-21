@extends('exports._layout', ['title' => 'Turnout Events'])

@section('body')
    <table>
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>Station</th>
                <th>Turnout</th>
                <th>Node</th>
                <th>Previous</th>
                <th>Current</th>
                <th>A</th>
                <th>B</th>
                <th>Transition</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row->source_timestamp?->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $row->turnout?->station?->code }}</td>
                    <td>
                        <strong>{{ $row->turnout?->code }}</strong><br>
                        <span style="color:#6b7280">{{ $row->turnout?->name }}</span>
                    </td>
                    <td>{{ $row->node?->node_id }}</td>
                    <td>
                        @if($row->previous_state)
                            <span class="badge {{ strtolower($row->previous_state) }}">
                                {{ $row->previous_state }}
                            </span>
                        @else - @endif
                    </td>
                    <td>
                        <span class="badge {{ strtolower($row->state) }}">{{ $row->state }}</span>
                    </td>
                    <td>{{ $row->channel_a ? 'Y' : 'N' }}</td>
                    <td>{{ $row->channel_b ? 'Y' : 'N' }}</td>
                    <td>{{ $row->is_transition ? 'Y' : 'N' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center;color:#9ca3af">No events.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
