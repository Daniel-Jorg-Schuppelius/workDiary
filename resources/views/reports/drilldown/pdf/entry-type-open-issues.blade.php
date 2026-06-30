@extends('reports.drilldown.pdf.layout')

@section('pdf-title', 'Auftragstyp Drilldown - Offene Punkte')
@section('pdf-heading', 'Auftragstyp Drilldown: Offene Punkte')

@section('pdf-meta')
    Auftragstyp: {{ $entryTypeLabel }}<br>
    Zeitraum: {{ $label }}
    @if ($escalatedOnly)
        <br>Filter: Nur eskalierte offene Punkte
    @endif
@endsection

@section('pdf-table')
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Titel</th>
                <th>Status</th>
                <th>Severity</th>
                <th>Fällig</th>
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
@endsection
