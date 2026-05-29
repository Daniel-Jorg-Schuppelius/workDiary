@extends('layouts.app')
@section('title', __('Drilldown: Defektprotokolle (Asset)'))
@section('nav-title', __('Drilldown: Defektprotokolle (Asset)'))

@section('content')
<x-page-shell>
    <x-page-toolbar>
        <x-slot:title>{{ __('Defektprotokolle') }}</x-slot:title>
        <x-slot:subtitle>
            {{ __('Bereich') }}: {{ $scopeLabel }} · {{ $label }}
        </x-slot:subtitle>
        <x-slot:actions>
            <x-icon-btn icon="download" tone="outline" size="sm"
                        :href="route('reports.assets.drilldown.protocols', array_filter($filters + ['export' => 'csv'], fn($v) => $v !== null && $v !== ''))"
                        show-label>CSV</x-icon-btn>
            <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                        :href="route('reports.assets.drilldown.protocols', array_filter($filters + ['export' => 'pdf'], fn($v) => $v !== null && $v !== ''))"
                        show-label>PDF</x-icon-btn>
            <x-icon-btn icon="arrow_back" tone="outline" size="sm"
                        :href="route('reports.assets', array_filter($filters, fn($v) => $v !== null && $v !== ''))"
                        show-label>{{ __('Zur Produktanalyse') }}</x-icon-btn>
        </x-slot:actions>
    </x-page-toolbar>

    <x-card>
        @if ($protocols->isEmpty())
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">fact_check</span>' :title="__('Keine Defektprotokolle für diesen Drilldown gefunden.')" />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Titel') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Zeitpunkt') }}</th>
                        <th>{{ __('Erstellt von') }}</th>
                        <th>{{ __('Auftrag') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($protocols as $protocol)
                    <tr>
                        <td class="font-medium">{{ $protocol->title }}</td>
                        <td><x-status-badge tone="ghost" outline>{{ $protocol->status->label() }}</x-status-badge></td>
                        <td>{{ $protocol->occurred_at?->format('d.m.Y H:i') ?? '—' }}</td>
                        <td>{{ $protocol->creator?->name ?? '—' }}</td>
                        <td>
                            <a href="{{ route('diary.show', $protocol->subject_id) }}" class="link link-hover">#{{ $protocol->subject_id }}</a>
                        </td>
                    </tr>
                @endforeach
            </x-table>

            @if ($protocols->hasPages())
                <div class="mt-4">{{ $protocols->links('pagination::simple-tailwind') }}</div>
            @endif
        @endif
    </x-card>
</x-page-shell>
@endsection
