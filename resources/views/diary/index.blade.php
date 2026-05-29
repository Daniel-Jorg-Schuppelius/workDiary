@extends('layouts.app')
@section('title', __('Auftragsbuch') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Alle Einträge'))

@section('content')
<x-page-shell overflow="clip">
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Auftragsbuch-Einträge erfassen, kommentieren und auswerten.')" />
    </x-slot:toolbar>
    {{-- Filter-Leiste --}}
    <x-filter-bar :action="route('diary.index')" :reset="array_filter($filters) ? route('diary.index') : null">
        <x-filter-field :label="__('Suche')" for="diary-q" class="flex-1 min-w-60">
            <input id="diary-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Inhalt oder Antwort …') }}" class="input input-bordered input-sm w-full">
        </x-filter-field>
        <x-filter-field :label="__('Status')" for="diary-status" class="min-w-40">
            <select id="diary-status" name="status" class="select select-bordered select-sm w-full">
                <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>{{ __('Alle') }}</option>
                <option value="2" @selected(($filters['status'] ?? '') === '2')>{{ __('Offen') }}</option>
                <option value="3" @selected(($filters['status'] ?? '') === '3')>{{ __('Problem') }}</option>
                <option value="1" @selected(($filters['status'] ?? '') === '1')>{{ __('Bestätigt') }}</option>
                <option value="-1" @selected(($filters['status'] ?? '') === '-1')>{{ __('Erledigt') }}</option>
            </select>
        </x-filter-field>
        @if (($allTags ?? collect())->isNotEmpty())
            <x-filter-field :label="__('Tag')" for="diary-tag">
                <select id="diary-tag" name="tag" class="select select-bordered select-sm">
                    <option value="">—</option>
                    @foreach ($allTags as $tag)
                        <option value="{{ $tag->sqid }}" @selected((string) ($filters['tag'] ?? '') === $tag->sqid)>{{ $tag->name }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        @endif
        @if (($entryTypes ?? collect())->isNotEmpty())
            <x-filter-field :label="__('Typ')" for="diary-entry-type" class="min-w-40">
                <select id="diary-entry-type" name="entry_type" class="select select-bordered select-sm w-full">
                    <option value="">{{ __('Alle Typen') }}</option>
                    @foreach ($entryTypes as $type)
                        <option value="{{ $type->sqid }}" @selected((string) ($filters['entry_type'] ?? '') === $type->sqid)>{{ $type->label }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        @endif
        <x-filter-field :label="__('Modus')" for="diary-mode" class="min-w-40">
            <select id="diary-mode" name="mode" class="select select-bordered select-sm w-full">
                <option value="">{{ __('Alle Modi') }}</option>
                <option value="{{ \App\Enums\Diary\Mode::Fixed->value }}" @selected(($filters['mode'] ?? '') === \App\Enums\Diary\Mode::Fixed->value)>{{ __('Terminiert') }}</option>
                <option value="{{ \App\Enums\Diary\Mode::Deadline->value }}" @selected(($filters['mode'] ?? '') === \App\Enums\Diary\Mode::Deadline->value)>{{ __('Deadline') }}</option>
                <option value="{{ \App\Enums\Diary\Mode::Window->value }}" @selected(($filters['mode'] ?? '') === \App\Enums\Diary\Mode::Window->value)>{{ __('Zeitfenster') }}</option>
                <option value="{{ \App\Enums\Diary\Mode::Recurring->value }}" @selected(($filters['mode'] ?? '') === \App\Enums\Diary\Mode::Recurring->value)>{{ __('Wiederkehrend') }}</option>
                <option value="{{ \App\Enums\Diary\Mode::Backlog->value }}" @selected(($filters['mode'] ?? '') === \App\Enums\Diary\Mode::Backlog->value)>{{ __('Backlog') }}</option>
            </select>
        </x-filter-field>
        <x-filter-field :label="__('Standort')" for="diary-location" class="min-w-36">
            <select id="diary-location" name="location" class="select select-bordered select-sm w-full">
                <option value="">{{ __('Alle Standorte') }}</option>
                @foreach (\App\Enums\Diary\LocationMode::cases() as $lm)
                    <option value="{{ $lm->value }}" @selected(($filters['location'] ?? '') === $lm->value)>{{ $lm->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <label class="flex items-center gap-2 pb-2">
            <input type="checkbox" id="mine" name="mine" value="1" @checked(!empty($filters['mine'])) class="checkbox checkbox-primary checkbox-sm">
            <span class="text-sm text-base-content/75">{{ __('Nur meine') }}</span>
        </label>
        <label class="flex items-center gap-2 pb-2">
            <input type="checkbox" id="archived" name="archived" value="1" @checked(!empty($filters['archived'])) class="checkbox checkbox-primary checkbox-sm">
            <span class="text-sm text-base-content/75">{{ __('Archivierte zeigen') }}</span>
        </label>
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
    <div class="flex-none grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['key' => 'all',   'label' => __('Gesamt'),   'tone' => 'primary'],
            ['key' => 'open',  'label' => __('Offen'),    'tone' => 'warning'],
            ['key' => 'alert', 'label' => __('Probleme'), 'tone' => 'error'],
            ['key' => 'done',  'label' => __('Erledigt'), 'tone' => 'success'],
        ] as $tile)
            <x-kpi-tile
                :label="$tile['label']"
                :value="$counts[$tile['key']]"
                :tone="$tile['tone']" />
        @endforeach
    </div>

    {{-- Eintrags-Liste --}}
    <div class="min-h-0 flex-1 overflow-y-auto pr-1 space-y-3">
        @forelse ($entries as $entry)
            <article class="grid gap-4 rounded-box border border-base-300 bg-base-100 p-4 shadow-xs transition hover:border-primary/30 md:grid-cols-[1fr_auto]">
                <div>
                    <div class="flex flex-wrap items-center gap-3 mb-3">
                        <span @class([
                            'badge badge-sm',
                            'badge-success' => $entry->statusTone() === 'done',
                            'badge-info' => $entry->statusTone() === 'progress',
                            'badge-warning' => $entry->statusTone() === 'open',
                            'badge-error' => $entry->statusTone() === 'alert',
                            'badge-ghost' => $entry->statusTone() === 'neutral',
                        ])>{{ $entry->statusLabel() }}</span>
                        @if ($entry->mode && $entry->mode !== \App\Enums\Diary\Mode::Fixed)
                            <x-status-badge tone="ghost" outline>{{ $entry->modeLabel() }}</x-status-badge>
                        @endif
                        @if ($entry->location_mode === \App\Enums\Diary\LocationMode::Remote)
                            <x-status-badge tone="ghost" outline>{{ __('Remote') }}</x-status-badge>
                        @elseif ($entry->location_mode === \App\Enums\Diary\LocationMode::Hybrid)
                            <x-status-badge tone="ghost" outline>{{ __('Hybrid') }}</x-status-badge>
                        @endif
                        @if ($entry->is_archived)
                            <x-status-badge tone="neutral">{{ __('Archiviert') }}</x-status-badge>
                        @endif
                        <span class="text-sm text-base-content/70">{{ optional($entry->user)->name ?? '—' }}</span>
                    </div>
                    <p class="text-base leading-relaxed text-base-content">
                        @php
                            $snippet = truncate($entry->content, 240);
                            $needle = trim((string) ($filters['q'] ?? ''));
                        @endphp
                        @if ($needle !== '')
                            {!! preg_replace('/(' . preg_quote($needle, '/') . ')/i', '<mark class="bg-warning/40 px-0.5 rounded">$1</mark>', e($snippet)) !!}
                        @else
                            {{ $snippet }}
                        @endif
                    </p>
                    @if ($entry->tags->isNotEmpty())
                        <div class="mt-2 flex flex-wrap gap-1">
                            @foreach ($entry->tags as $tag)
                                <span class="badge badge-outline badge-sm" @if ($tag->color) style="border-color: {{ $tag->color }}; color: {{ $tag->color }};" @endif>#{{ $tag->name }}</span>
                            @endforeach
                        </div>
                    @endif
                    <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-base-content/65">
                        @switch($entry->mode)
                            @case(\App\Enums\Diary\Mode::Deadline)
                                @if ($entry->due_date)
                                    <span>{{ __('Fällig bis') }} {{ $entry->due_date->format('d.m.Y') }}</span>
                                @endif
                                @break
                            @case(\App\Enums\Diary\Mode::Window)
                                @if ($entry->window_start_date)
                                    <span>{{ __('Fenster') }} {{ $entry->window_start_date->format('d.m.Y') }}@if ($entry->window_end_date) – {{ $entry->window_end_date->format('d.m.Y') }}@endif</span>
                                @endif
                                @break
                            @case(\App\Enums\Diary\Mode::Backlog)
                                <span>{{ __('Backlog — kein Datum') }}</span>
                                @break
                            @default
                                @if ($entry->start_at)
                                    <span>{{ __('Von') }} {{ $entry->start_at->format('d.m.Y H:i') }}</span>
                                @endif
                                @if ($entry->end_at)
                                    <span>{{ __('Bis') }} {{ $entry->end_at->format('d.m.Y H:i') }}</span>
                                @endif
                        @endswitch
                        <span>Erstellt {{ $entry->created_at->diffForHumans() }}</span>
                    </div>
                </div>
                <div class="flex flex-col gap-2 md:items-end md:justify-between">
                    <x-icon-btn icon="visibility" tone="outline" size="sm"
                                data-entry-modal-trigger
                                :href="route('diary.show', $entry)"
                                class="btn-primary"
                                show-label>{{ __('Details') }}</x-icon-btn>
                    @can('update', $entry)
                        <x-icon-btn icon="edit" size="sm"
                                    data-entry-modal-trigger
                                    :href="route('diary.edit', $entry)"
                                    show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                    @endcan
                </div>
            </article>
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
    @if ($entries->hasPages())
        <div class="flex-none">
            {{ $entries->links('pagination::simple-tailwind') }}
        </div>
    @endif
</x-page-shell>
@endsection
