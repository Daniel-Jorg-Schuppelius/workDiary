@extends('layouts.app')
@section('title', __('Kanban') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Kanban'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
@php
    $tabs = [
        'mine' => ['label' => __('Meine'), 'url' => route('kanban.index', ['scope' => 'mine'])],
        'team' => ['label' => __('Team'),  'url' => route('kanban.index', ['scope' => 'team'])],
    ];
    $activeTab = $teamScope ? 'team' : 'mine';
@endphp
<x-index-page :subtitle="__('Auftragsbuch-Einträge nach ihrem fachlichen Status visualisieren.')">
    {{-- Toolbar --}}
    <x-slot:actions>
        <x-icon-btn icon="add" tone="primary" size="sm"
                    data-entry-modal-trigger
                    :href="route('diary.create')"
                    show-label>{{ __('Eintrag') }}</x-icon-btn>
    </x-slot:actions>

    {{-- Tabs --}}
    @include('duties._tab_strip', ['tabs' => $tabs, 'tab' => $activeTab])

    @if ($isLimited)
        <div class="rounded-box border border-warning/40 bg-warning/10 px-4 py-3 text-sm text-base-content/80">
            {{ __('Es werden maximal :count Einträge angezeigt. Verfeinere den Zeitraum oder die Ansicht für vollständigere Ergebnisse.', ['count' => 200]) }}
        </div>
    @endif

    {{-- Board --}}
    <div class="grid min-h-0 flex-1 grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3"
         data-kanban-board>
        @foreach ($columns as $statusCode => $col)
            @php $items = $byStatus->get($statusCode, collect()); @endphp
            <section class="flex min-h-0 flex-col rounded-box border border-base-300 bg-base-100 shadow-xs"
                     data-kanban-column data-status="{{ $statusCode }}">
                <header class="flex items-center justify-between border-b border-base-300 px-3 py-2">
                    <div class="flex items-center gap-2">
                        <span class="wd-week-legend wd-week-entry--{{ $col['tone'] }}"></span>
                        <span class="font-['Space_Grotesk'] font-semibold">{{ __($col['label']) }}</span>
                    </div>
                    <span class="badge badge-sm" data-kanban-count>{{ $items->count() }}</span>
                </header>
                <div class="flex min-h-0 flex-1 flex-col gap-2 overflow-auto p-2" data-kanban-list>
                    @foreach ($items as $entry)
                        @include('kanban._card', ['entry' => $entry])
                    @endforeach
                    <p class="px-2 py-4 text-center text-xs text-base-content/50 {{ $items->isEmpty() ? '' : 'hidden' }}" data-kanban-empty>{{ __('Keine Einträge') }}</p>
                </div>
            </section>
        @endforeach
    </div>
</x-index-page>

@endsection
