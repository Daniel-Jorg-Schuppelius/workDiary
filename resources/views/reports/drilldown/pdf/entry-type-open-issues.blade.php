<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Auftragstyp Drilldown - Offene Punkte</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        .meta { margin: 0 0 12px; color: #374151; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; }
    </style>
</head>
<body>
    <h1>Auftragstyp Drilldown: Offene Punkte</h1>
    <p class="meta">
        Auftragstyp: {{ $entryTypeLabel }}<br>
        Zeitraum: {{ $label }}
        @if ($escalatedOnly)
            <br>Filter: Nur eskalierte offene Punkte
        @endif
    </p>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Titel</th>
                <th>Status</th>
                <th>Severity</th>
                <th>Faellig</th>
                <th>Zugewiesen</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($issues as $issue)
                <tr>
                    <td>{{ $issue->id }}</td>
                    <td>{{ $issue->title }}</td>
                    <td>{{ $issue->status->label() }}</td>
                    <td>{{ $issue->severity->label() }}</td>
                    <td>{{ $issue->due_at?->format('Y-m-d') ?? '' }}</td>
                    <td>{{ $issue->assignee?->name ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
