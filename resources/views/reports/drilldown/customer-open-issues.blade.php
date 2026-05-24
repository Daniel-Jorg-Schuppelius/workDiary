@extends('layouts.app')
@section('title', __('Drilldown: Offene Punkte'))
@section('nav-title', __('Drilldown: Offene Punkte'))

@section('content')
<x-page-shell>
    <x-page-toolbar>
        <x-slot:title>{{ __('Offene Punkte') }}</x-slot:title>
        <x-slot:subtitle>
            {{ __('Kunde') }}: {{ $customer?->name ?? ('#' . $customerId) }} · {{ $label }}
            @if ($escalatedOnly)
                · {{ __('Nur eskaliert') }}
            @endif
        </x-slot:subtitle>
        <x-slot:actions>
            <x-icon-btn icon="arrow_back" tone="outline" size="sm"
                        :href="route('reports.customers', array_filter(['project_id' => $projectId, 'user_id' => $userId]))"
                        show-label>{{ __('Zur Kundenanalyse') }}</x-icon-btn>
        </x-slot:actions>
    </x-page-toolbar>

    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
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
                        <td><span class="badge badge-sm badge-outline">{{ $issue->status->label() }}</span></td>
                        <td><span class="badge badge-sm badge-outline">{{ $issue->severity->label() }}</span></td>
                        <td>{{ $issue->due_at?->format('d.m.Y') ?? '—' }}</td>
                        <td>{{ $issue->assignee?->name ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-table>

            @if ($issues->hasPages())
                <div class="mt-4">{{ $issues->links('pagination::simple-tailwind') }}</div>
            @endif
        @endif
    </div>
</x-page-shell>
@endsection
