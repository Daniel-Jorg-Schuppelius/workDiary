@extends('layouts.app')
@section('title', __('Auftragsbuch') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Alle Einträge'))

@section('content')
<x-index-page overflow="clip" :subtitle="__('Auftragsbuch-Einträge erfassen, kommentieren und auswerten.')">
    {{-- Filter-Leiste --}}
    <x-filter-bar :action="route('diary.index')" :reset="array_filter($filters) ? route('diary.index') : null">
        @include('diary._filter_fields', ['idPrefix' => 'diary'])
        <x-slot:extra>
            <div class="dropdown dropdown-end">
                <label tabindex="0" class="btn btn-sm btn-outline gap-1">
                    <x-icon name="download" /><span>{{ __('Export') }}</span>
                </label>
                <ul tabindex="0" class="dropdown-content menu z-50 mt-1 w-44 rounded-box border border-base-300 bg-base-100 p-2 shadow">
                    <li><a href="{{ route('diary.export.csv', $filters) }}">{{ __('CSV') }}</a></li>
                    <li><a href="{{ route('diary.export.pdf', $filters) }}" target="_blank">{{ __('PDF (Druckansicht)') }}</a></li>
                </ul>
            </div>
            <x-help-button topic="diary-entries.create" />
        </x-slot:extra>
    </x-filter-bar>

    {{-- Zähler --}}
    <div class="flex-none grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ([
            ['key' => 'all',       'label' => __('Gesamt'),         'tone' => 'primary'],
            ['key' => 'planned',   'label' => __('Geplant'),        'tone' => 'info'],
            ['key' => 'active',    'label' => __('In Bearbeitung'), 'tone' => 'warning'],
            ['key' => 'done',      'label' => __('Abgeschlossen'),  'tone' => 'success'],
            ['key' => 'cancelled', 'label' => __('Storniert'),      'tone' => 'neutral'],
        ] as $tile)
            <x-kpi-tile
                :label="$tile['label']"
                :value="$counts[$tile['key']]"
                :tone="$tile['tone']" />
        @endforeach
    </div>

    {{-- Eintrags-Liste --}}
    <div class="min-h-0 flex-1 overflow-y-auto space-y-3">
        @forelse ($entries as $entry)
            @include('diary._entry_card', ['entry' => $entry, 'filters' => $filters])
        @empty
            <x-empty-state framed
                icon='<span class="material-symbols-outlined" aria-hidden="true">menu_book</span>'
                :title="__('Keine Einträge gefunden')"
                :message="array_filter($filters) ? __('Versuche, die Filter zu erweitern.') : null">
                @if (array_filter($filters))
                    <x-slot:action>
                        <x-icon-btn icon="restart_alt" size="sm" :href="route('diary.index')" show-label>{{ __('Filter zurücksetzen') }}</x-icon-btn>
                    </x-slot:action>
                @endif
            </x-empty-state>
        @endforelse
    </div>

    {{-- Pagination --}}
    <x-pagination :paginator="$entries" standing />
</x-index-page>
@endsection
