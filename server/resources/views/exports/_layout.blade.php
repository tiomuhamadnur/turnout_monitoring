{{--
    Shared layout for all PDF exports. dompdf can't load external CSS or
    most webfonts, so everything is inlined here. Landscape A4 by default
    (set in the controller via setPaper()).
--}}
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Export' }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9.5pt;
            color: #111827;
            margin: 0;
        }
        h1 {
            font-size: 14pt;
            margin: 0 0 4pt 0;
            color: #1f2937;
        }
        .meta {
            font-size: 8pt;
            color: #6b7280;
            margin-bottom: 12pt;
        }
        .filters {
            font-size: 8pt;
            color: #374151;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            padding: 4pt 6pt;
            margin-bottom: 8pt;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 3pt 5pt;
            text-align: left;
            vertical-align: top;
        }
        thead th {
            background: #1f2937;
            color: #fff;
            font-size: 8.5pt;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        tbody tr:nth-child(even) td { background: #f3f4f6; }
        .badge {
            display: inline-block;
            padding: 1pt 5pt;
            border-radius: 8pt;
            font-size: 7.5pt;
            font-weight: bold;
        }
        .badge.normal  { background: #10B981; color: #fff; }
        .badge.reverse { background: #F59E0B; color: #1f2937; }
        .badge.failure { background: #EF4444; color: #fff; }
        .badge.active  { background: #EF4444; color: #fff; }
        .badge.resolved{ background: #6b7280; color: #fff; }
        .footer {
            position: fixed;
            bottom: 6pt;
            left: 0;
            right: 0;
            text-align: right;
            color: #9ca3af;
            font-size: 7.5pt;
        }
        .footer .page::after { content: counter(page) " / " counter(pages); }
    </style>
</head>
<body>
    <h1>@yield('title', $title ?? 'Export')</h1>
    <div class="meta">
        Generated {{ $generatedAt->format('Y-m-d H:i:s') }} —
        MRT Turnout Monitoring
    </div>

    @if(!empty($filters))
        <div class="filters">
            <strong>Filters:</strong>
            @foreach($filters as $k => $v)
                @if($v !== '' && $v !== null && $v !== false)
                    <span style="margin-right: 6pt">
                        {{ $k }}=<strong>{{ is_bool($v) ? ($v ? 'true' : 'false') : $v }}</strong>
                    </span>
                @endif
            @endforeach
        </div>
    @endif

    @yield('body')

    <div class="footer">
        Page <span class="page"></span>
    </div>
</body>
</html>
