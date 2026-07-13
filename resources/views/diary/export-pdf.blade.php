<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Auftragsbuch-Export') }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 11px; color: #111; margin: 24px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .meta { color: #555; font-size: 10px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 6px 8px; border: 1px solid #ddd; vertical-align: top; text-align: left; }
        th { background: #f5f5f5; font-weight: 600; }
        tr:nth-child(even) td { background: #fafafa; }
        .status { white-space: nowrap; font-weight: 600; }
        .status-done { color: #047857; }
        .status-alert { color: #b91c1c; }
        .status-open { color: #b45309; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
        .actions { margin: 8px 0 16px; }
        .btn { padding: 6px 12px; border: 1px solid #555; background: #fff; cursor: pointer; }
    </style>
</head>
<body>
    <div class="actions no-print">
        <button class="btn" data-print>{{ __('Drucken / Als PDF speichern') }}</button>
    </div>

    <h1>{{ __('Auftragsbuch-Export') }}</h1>
    <div class="meta">
        {{ __('Erstellt am') }} {{ $generatedAt->fdatetime() }} —
        {{ count($entries) }} {{ __('Einträge') }}
        @if (! empty(array_filter($filters)))
            — {{ __('Filter aktiv') }}
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Mitarbeiter') }}</th>
                <th>{{ __('Von') }}</th>
                <th>{{ __('Bis') }}</th>
                <th>{{ __('Inhalt') }}</th>
                <th>{{ __('Antwort') }}</th>
                <th>{{ __('Tags') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($entries as $entry)
                <tr>
                    <td>{{ $entry->id }}</td>
                    <td class="status status-{{ $entry->statusTone() }}">{{ $entry->statusLabel() }}</td>
                    <td>{{ optional($entry->user)->name ?? '—' }}</td>
                    <td>{{ optional($entry->start_at)->fdatetime() }}</td>
                    <td>{{ optional($entry->end_at)->fdatetime() }}</td>
                    <td>{{ $entry->content }}</td>
                    <td>{{ $entry->response }}</td>
                    <td>{{ $entry->tags->pluck('name')->implode(', ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@include('partials.print-script')
</body>
</html>
