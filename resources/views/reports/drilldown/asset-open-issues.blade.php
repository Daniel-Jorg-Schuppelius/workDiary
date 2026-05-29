@extends('layouts.app')
@section('title', __('Drilldown: Offene Punkte (Asset)'))
@section('nav-title', __('Drilldown: Offene Punkte (Asset)'))

@section('content')
<x-page-shell>
    <x-page-toolbar>
        <x-slot:title>{{ __('Offene Punkte') }}</x-slot:title>
        <x-slot:subtitle>
            {{ __('Bereich') }}: {{ $scopeLabel }} · {{ $label }}
            @if ($escalatedOnly)
                · {{ __('Nur eskaliert') }}
            @endif
        </x-slot:subtitle>
        <x-slot:actions>
            <x-icon-btn icon="download" tone="outline" size="sm"
                        :href="route('reports.assets.drilldown.open-issues', array_filter($filters + ['escalated' => $escalatedOnly ? 1 : null, 'export' => 'csv'], fn($v) => $v !== null && $v !== ''))"
                        show-label>CSV</x-icon-btn>
            <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                        :href="route('reports.assets.drilldown.open-issues', array_filter($filters + ['escalated' => $escalatedOnly ? 1 : null, 'export' => 'pdf'], fn($v) => $v !== null && $v !== ''))"
                        show-label>PDF</x-icon-btn>
            <x-icon-btn icon="arrow_back" tone="outline" size="sm"
                        :href="route('reports.assets', array_filter($filters, fn($v) => $v !== null && $v !== ''))"
                        show-label>{{ __('Zur Produktanalyse') }}</x-icon-btn>
        </x-slot:actions>
    </x-page-toolbar>

    <x-card>
        @if ($issues->isEmpty())
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">error_outline</span>' :title="__('Keine offenen Punkte für diesen Drilldown gefunden.')" />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Asset') }}</th>
                        <th>{{ __('Titel') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Severity') }}</th>
                        <th>{{ __('Fällig') }}</th>
                        <th>{{ __('Zugewiesen') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($issues as $issue)
                    <tr>
                        <td>#{{ $issue->subject_id }}</td>
                        <td class="font-medium">{{ $issue->title }}</td>
                        <td><x-status-badge tone="ghost" outline>{{ $issue->status->label() }}</x-status-badge></td>
                        <td><x-status-badge tone="ghost" outline>{{ $issue->severity->label() }}</x-status-badge></td>
                        <td>{{ $issue->due_at?->format('d.m.Y') ?? '—' }}</td>
                        <td>{{ $issue->assignee?->name ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-table>

            @if ($issues->hasPages())
                <div class="mt-4">{{ $issues->links('pagination::simple-tailwind') }}</div>
            @endif
        @endif
    </x-card>
</x-page-shell>
@endsection
