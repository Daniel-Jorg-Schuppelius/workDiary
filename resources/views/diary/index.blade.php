{{--
  Created on   : Wed Apr 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Auftragsbuch') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Alle Einträge'))

@section('content')
<x-index-page overflow="clip" :subtitle="__('Auftragsbuch-Einträge erfassen, kommentieren und auswerten.')">
    {{-- Filter-Leiste --}}
    <x-filter-bar :action="route('diary.index')" :reset="array_filter($filters) ? route('diary.index') : null">
        @include('diary._filter_fields', ['idPrefix' => 'diary'])
        <x-slot:extra>
            <x-action-menu icon="download" tone="outline" :label="__('Export')">
                <x-button tone="ghost" :href="route('diary.export.csv', $filters)">{{ __('CSV') }}</x-button>
                <x-button tone="ghost" :href="route('diary.export.pdf', $filters)" target="_blank">{{ __('PDF (Druckansicht)') }}</x-button>
            </x-action-menu>
            {{-- Periodenaufträge (Feature 198): Prüf-Warteschlange und Folgeperiode gesammelt. --}}
            @if (auth()->user()?->can(\App\Enums\User\Permission::OrderReview->value))
                <x-button tone="outline" size="sm" icon="fact_check" :href="route('diary.review-queue')">{{ __('diary.review.queue.title') }}</x-button>
            @endif
            @if (auth()->user()?->can(\App\Enums\User\Permission::DiaryCreateForOthers->value) && auth()->user()?->can(\App\Enums\User\Permission::DiaryViewAny->value))
                <x-button tone="outline" size="sm" icon="event_repeat" :href="route('diary.follow-up.index')">{{ __('diary.follow_up.bulk.title') }}</x-button>
            @endif
            <x-help-button topic="diary-entries.create" />
        </x-slot:extra>
    </x-filter-bar>

    {{-- Zähler --}}
    <div class="flex-none grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        {{-- Kacheln filtern die Liste nach Status (ux-pattern-katalog §4); die Zahlen bleiben über dem Gesamtbestand stehen. --}}
        @foreach ([
            ['key' => 'all',       'status' => null,     'label' => __('Gesamt'),         'tone' => 'primary'],
            ['key' => 'planned',   'status' => '2',      'label' => __('Geplant'),        'tone' => 'info'],
            ['key' => 'active',    'status' => 'active', 'label' => __('In Bearbeitung'), 'tone' => 'warning'],
            ['key' => 'done',      'status' => 'done',   'label' => __('Abgeschlossen'),  'tone' => 'success'],
            ['key' => 'cancelled', 'status' => '8',      'label' => __('Storniert'),      'tone' => 'neutral'],
        ] as $tile)
            <x-kpi-tile
                :label="$tile['label']"
                :value="$counts[$tile['key']]"
                :tone="$tile['tone']"
                :href="route('diary.index', array_filter(array_merge($filters, ['status' => $tile['status']]), static fn ($v) => $v !== null && $v !== ''))"
                :active="$tile['status'] !== null && ($filters['status'] ?? '') === $tile['status']" />
        @endforeach
    </div>

    {{-- Eintrags-Liste; Mehrfachauswahl „Zuständigkeit ändern“ (Feature 202, MVP-1648) für bearbeitbare Aufträge. --}}
    <form method="POST" action="{{ route('diary.bulk-assign') }}" data-bulk-form class="min-h-0 flex-1 flex flex-col gap-3">
    @csrf
    <x-bulk-toolbar :label="__('delegation.reassign.selected')" icon="move_up">
        <x-slot:actions>
            <select name="assignee" class="select select-sm select-bordered w-48" aria-label="{{ __('delegation.reassign.choose') }}" required>
                <option value="">{{ __('delegation.reassign.choose') }}</option>
                @foreach ($assigneeOptions ?? [] as $assigneeOption)
                    <option value="{{ $assigneeOption->sqid }}">{{ $assigneeOption->name }}</option>
                @endforeach
            </select>
            <x-button type="submit" tone="primary" size="sm" icon="move_up">{{ __('delegation.reassign.submit') }}</x-button>
        </x-slot:actions>
    </x-bulk-toolbar>
    @error('assignee')<p class="text-error text-xs">{{ $message }}</p>@enderror
    @error('ids')<p class="text-error text-xs">{{ $message }}</p>@enderror
    <div class="min-h-0 flex-1 overflow-y-auto space-y-3">
        @forelse ($entries as $entry)
            @include('diary._entry_card', ['entry' => $entry, 'filters' => $filters, 'bulkAssign' => true])
        @empty
            <x-empty-state framed
                icon="menu_book"
                :title="__('Keine Einträge gefunden')"
                :message="array_filter($filters) ? __('Versuchen Sie, die Filter zu erweitern.') : null">
                @if (array_filter($filters))
                    <x-slot:action>
                        <x-icon-btn icon="restart_alt" size="sm" :href="route('diary.index')" show-label>{{ __('Filter zurücksetzen') }}</x-icon-btn>
                    </x-slot:action>
                @endif
            </x-empty-state>
        @endforelse
    </div>
    </form>

    {{-- Pagination --}}
    <x-pagination :paginator="$entries" standing />
</x-index-page>
@endsection
