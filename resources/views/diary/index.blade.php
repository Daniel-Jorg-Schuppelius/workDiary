@extends('layouts.app')
@section('title', __('Tagebuch') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Alle Einträge'))

@section('content')
<x-page-shell overflow="clip">
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
                        <option value="{{ $tag->id }}" @selected((int) ($filters['tag'] ?? 0) === $tag->id)>{{ $tag->name }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        @endif
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
                <label tabindex="0" class="btn btn-sm btn-outline">↓ {{ __('Export') }}</label>
                <ul tabindex="0" class="dropdown-content menu z-50 mt-1 w-44 rounded-box border border-base-300 bg-base-100 p-2 shadow">
                    <li><a href="{{ route('diary.export.csv', $filters) }}">{{ __('CSV') }}</a></li>
                    <li><a href="{{ route('diary.export.pdf', $filters) }}" target="_blank">{{ __('PDF (Druckansicht)') }}</a></li>
                </ul>
            </div>
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
                        @if ($entry->is_archived)
                            <span class="badge badge-sm badge-neutral">{{ __('Archiviert') }}</span>
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
                        @if ($entry->start_at)
                            <span>{{ __('Von') }} {{ $entry->start_at->format('d.m.Y H:i') }}</span>
                        @endif
                        @if ($entry->end_at)
                            <span>{{ __('Bis') }} {{ $entry->end_at->format('d.m.Y H:i') }}</span>
                        @endif
                        <span>Erstellt {{ $entry->created_at->diffForHumans() }}</span>
                    </div>
                </div>
                <div class="flex flex-col gap-2 md:items-end md:justify-between">
                    <a href="{{ route('diary.show', $entry) }}" data-entry-modal-trigger class="btn btn-outline btn-primary btn-sm text-center">{{ __('Details') }}</a>
                    @can('update', $entry)
                        <a href="{{ route('diary.edit', $entry) }}" data-entry-modal-trigger class="btn btn-ghost btn-sm text-center">{{ __('Bearbeiten') }}</a>
                    @endcan
                </div>
            </article>
        @empty
            <div class="rounded-box border border-dashed border-base-300 bg-base-100 p-8 text-center text-base-content/70">
                Keine Einträge gefunden.
                @if (array_filter($filters))
                    <a href="{{ route('diary.index') }}" class="ml-2 text-primary underline">{{ __('Filter zurücksetzen') }}</a>
                @endif
            </div>
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
