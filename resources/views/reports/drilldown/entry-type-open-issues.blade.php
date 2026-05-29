@extends('layouts.app')
@section('title', __('Drilldown: Offene Punkte (Auftragstyp)'))
@section('nav-title', __('Drilldown: Offene Punkte (Auftragstyp)'))

@section('content')
<x-page-shell>
    <x-page-toolbar>
        <x-slot:title>{{ __('Offene Punkte') }}</x-slot:title>
        <x-slot:subtitle>
            {{ __('Auftragstyp') }}: {{ $entryType?->label ?? ('#' . $entryTypeId) }} · {{ $label }}
            @if ($escalatedOnly)
                · {{ __('Nur eskaliert') }}
            @endif
        </x-slot:subtitle>
        <x-slot:actions>
            <x-icon-btn icon="download" tone="outline" size="sm"
                        :href="route('reports.entry-types.drilldown.open-issues', array_filter(['entry_type_id' => $entryTypeId, 'customer_id' => $customerId, 'user_id' => $userId, 'status' => $statusFilter, 'escalated' => $escalatedOnly ? 1 : null, 'export' => 'csv']))"
                        show-label>CSV</x-icon-btn>
            <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                        :href="route('reports.entry-types.drilldown.open-issues', array_filter(['entry_type_id' => $entryTypeId, 'customer_id' => $customerId, 'user_id' => $userId, 'status' => $statusFilter, 'escalated' => $escalatedOnly ? 1 : null, 'export' => 'pdf']))"
                        show-label>PDF</x-icon-btn>
            <x-icon-btn icon="arrow_back" tone="outline" size="sm"
                        :href="route('reports.entry-types', array_filter(['customer_id' => $customerId, 'user_id' => $userId, 'entry_type_id' => $entryTypeId, 'status' => $statusFilter]))"
                        show-label>{{ __('Zur Auftragstypanalyse') }}</x-icon-btn>
        </x-slot:actions>
    </x-page-toolbar>

    <x-card>
        @if ($issues->isEmpty())
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">error_outline</span>' :title="__('Keine offenen Punkte für diesen Drilldown gefunden.')" />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Titel') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Severity') }}</th>
                        <th>{{ __('Fällig') }}</th>
                        <th>{{ __('Zugewiesen') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($issues as $issue)
                    <tr>
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
