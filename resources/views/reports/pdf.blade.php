@use('App\Support\ReportFormatter')
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        @page { margin: 26px 28px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #111827; }
        h1 { margin: 0; font-size: 17px; color: #1e3a8a; }
        .meta { margin: 3px 0 0; }
        .muted { color: #6b7280; }
        table.summary { width: 100%; border-collapse: collapse; margin: 12px 0; }
        table.summary td { border: 1px solid #e5e7eb; padding: 5px 8px; width: 33.33%; vertical-align: top; }
        .label { color: #6b7280; font-size: 8px; }
        .value { font-size: 11px; font-weight: bold; color: #1e3a8a; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data thead { display: table-header-group; }
        table.data th { background: #1e3a8a; color: #ffffff; text-align: left; padding: 5px 6px; font-size: 8.5px; }
        table.data td { border-bottom: 1px solid #e5e7eb; padding: 4px 6px; vertical-align: top; }
        table.data tr { page-break-inside: avoid; }
        .r { text-align: right; }
        .aman { color: #15803d; font-weight: bold; }
        .menipis { color: #a16207; font-weight: bold; }
        .habis { color: #b91c1c; font-weight: bold; }
        .note { margin: 3px 0 0; color: #6b7280; font-size: 8px; }
    </style>
</head>
<body>
    <h1>{{ $report['title'] }}</h1>
    <p class="meta"><strong>{{ $storeName }}</strong></p>
    @foreach ($report['filters_text'] as $line)
        <p class="meta muted">{{ $line }}</p>
    @endforeach
    <p class="meta muted">Dibuat: {{ $generatedAt }}</p>

    <table class="summary">
        @foreach (array_chunk($report['summary'], 3) as $chunk)
            <tr>
                @foreach ($chunk as $item)
                    <td>
                        <div class="label">{{ $item['label'] }}</div>
                        <div class="value">{{ ReportFormatter::display($item['value'], $item['type']) }}</div>
                    </td>
                @endforeach
            </tr>
        @endforeach
    </table>

    <table class="data">
        <thead>
            <tr>
                @foreach ($report['columns'] as $col)
                    <th class="{{ in_array($col['type'], ['money', 'number'], true) ? 'r' : '' }}">{{ $col['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                <tr>
                    @foreach ($report['columns'] as $col)
                        @php($value = $row[$col['key']] ?? null)
                        <td class="{{ in_array($col['type'], ['money', 'number'], true) ? 'r' : '' }} {{ $col['type'] === 'status' ? $value : '' }}">
                            {{ ReportFormatter::display($value, $col['type']) }}
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($report['columns']) }}" class="muted" style="text-align: center; padding: 14px;">
                        Tidak ada data pada filter ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @foreach ($report['notes'] as $note)
        <p class="note">* {{ $note }}</p>
    @endforeach
</body>
</html>
