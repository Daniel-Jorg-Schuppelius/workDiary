{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : board.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Projektboard (Feature 064, P1-Fundament — Board-UI-Ausbau in P3):
     Aktivierung, Spalten mit Kategorie/WIP, Einstellungen (lock_version). --}}

@extends('layouts.app')

@section('title', __('Projektboard') . ' — ' . $project->name)
@section('nav-title', __('Projektboard'))

@section('content')
<x-page-shell>
    <x-page-toolbar>
        <x-slot:title>{{ __('Projektboard') }} — {{ $project->name }}</x-slot:title>
        <x-slot:subtitle>{{ $board?->description ?? __('Produkt-Backlog, Board und Sprints für dieses Projekt.') }}</x-slot:subtitle>
        <x-slot:actions>
            <x-icon-btn icon="low_priority" tone="ghost" size="sm" :href="route('agile.backlog', $project)" show-label>{{ __('Produkt-Backlog') }}</x-icon-btn>
            <x-icon-btn icon="sprint" tone="ghost" size="sm" :href="route('agile.sprints', $project)" show-label>{{ __('Sprints') }}</x-icon-btn>
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('projects.show', $project)" show-label>{{ __('Zum Projekt') }}</x-icon-btn>
        </x-slot:actions>
    </x-page-toolbar>

    @if ($board !== null && $sprints->isNotEmpty())
        {{-- Sprint-Kontext: Board zeigt dann nur die Items des Sprints. --}}
        <form method="GET" action="{{ route('agile.board', $project) }}" class="flex items-center gap-2">
            <select name="sprint" class="select select-sm select-bordered" aria-label="{{ __('Sprint-Kontext') }}" onchange="this.form.submit()">
                <option value="">{{ __('Alle Elemente') }}</option>
                @foreach ($sprints as $candidate)
                    <option value="{{ $candidate->sqid }}" @selected($sprint?->id === $candidate->id)>{{ $candidate->name }}</option>
                @endforeach
            </select>
            <noscript><x-icon-btn icon="filter_alt" tone="ghost" size="sm" type="submit" :label="__('Filtern')" /></noscript>
        </form>
    @endif

    @if ($board === null)
        <x-empty-state icon="view_kanban" framed
                       :title="__('Für dieses Projekt ist noch kein Board aktiviert.')"
                       :message="__('Die Aktivierung legt ein Board mit vier Standardspalten an (Bereit, In Arbeit, Review, Erledigt).')">
            <x-slot:action>
                @if ($canManage)
                    <form method="POST" action="{{ route('agile.activate', $project) }}" class="flex items-center gap-2">
                        @csrf
                        <select name="method" class="select select-sm select-bordered">
                            <option value="kanban">{{ __('Kanban') }}</option>
                            <option value="scrum">{{ __('Scrum') }}</option>
                        </select>
                        <x-icon-btn icon="rocket_launch" tone="primary" size="sm" type="submit" show-label>{{ __('Board aktivieren') }}</x-icon-btn>
                    </form>
                @endif
            </x-slot:action>
        </x-empty-state>
    @else
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($board->columns as $column)
                <div class="rounded-box border border-base-300 bg-base-100 p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <h2 class="font-['Space_Grotesk'] text-sm font-semibold">{{ $column->name }}</h2>
                        <span class="inline-flex items-center gap-1">
                            <x-status-badge tone="neutral" size="xs">{{ $column->category->label() }}</x-status-badge>
                            @if ($column->wip_limit !== null)
                                <x-term glossary="wip"><x-status-badge tone="info" size="xs">{{ __('WIP :n', ['n' => $column->wip_limit]) }}</x-status-badge></x-term>
                            @endif
                        </span>
                    </div>
                    @if ($column->workItems->isEmpty())
                        <p class="text-xs text-base-content/50">{{ __('Keine Elemente.') }}</p>
                    @else
                        <ul class="space-y-1">
                            @foreach ($column->workItems as $item)
                                <li class="rounded border px-2 py-1 text-sm {{ $item->isBlocked() ? 'border-error' : 'border-base-300' }}">
                                    <div class="flex items-center justify-between gap-1">
                                        <span>
                                            {{ $item->task?->title ?? '—' }}
                                            <x-status-badge tone="neutral" size="xs">{{ $item->item_type->label() }}</x-status-badge>
                                            @if ($item->story_points !== null)
                                                <span class="badge badge-ghost badge-xs">{{ $item->story_points }} SP</span>
                                            @endif
                                            @php($bookedMinutes = (int) ($item->task?->time_entries_sum_minutes ?? 0))
                                            @if ($bookedMinutes > 0)
                                                <span class="badge badge-ghost badge-xs" title="{{ __('Gebuchte Zeit') }}">{{ intdiv($bookedMinutes, 60) }}:{{ str_pad((string) ($bookedMinutes % 60), 2, '0', STR_PAD_LEFT) }} h</span>
                                            @endif
                                            @if ($item->isBlocked())
                                                <x-status-badge tone="error" size="xs" :title="$item->blocked_reason">{{ __('blockiert') }}</x-status-badge>
                                            @endif
                                        </span>
                                    </div>
                                    {{-- Verschieben ohne JS (A11y-Fallback — Drag dockt am selben Endpoint an). --}}
                                    <form method="POST" action="{{ route('agile.items.move', [$project, $item]) }}" class="mt-1 flex items-center gap-1">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="lock_version" value="{{ $item->lock_version }}">
                                        <select name="column" class="select select-xs select-bordered flex-1" aria-label="{{ __('Verschieben nach') }}">
                                            @foreach ($board->columns as $target)
                                                <option value="{{ $target->sqid }}" @selected($target->id === $column->id)>{{ $target->name }}</option>
                                            @endforeach
                                        </select>
                                        <x-icon-btn icon="arrow_forward" tone="ghost" size="xs" type="submit" :label="__('Verschieben')" />
                                    </form>
                                    @if ($item->isBlocked())
                                        <form method="POST" action="{{ route('agile.items.unblock', [$project, $item]) }}" class="mt-1">
                                            @csrf
                                            <x-icon-btn icon="lock_open" tone="ghost" size="xs" type="submit" show-label>{{ __('Blockierung aufheben') }}</x-icon-btn>
                                        </form>
                                    @else
                                        <details class="mt-1">
                                            <summary class="cursor-pointer text-xs text-base-content/60">{{ __('Blockieren…') }}</summary>
                                            <form method="POST" action="{{ route('agile.items.block', [$project, $item]) }}" class="mt-1 flex items-center gap-1">
                                                @csrf
                                                <input name="reason" required minlength="3" maxlength="300" placeholder="{{ __('Grund (Pflicht)') }}" class="input input-xs input-bordered flex-1">
                                                <x-icon-btn icon="block" tone="ghost" size="xs" type="submit" :label="__('Blockieren')" />
                                            </form>
                                        </details>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($canManage)
            <x-card :title="__('Board-Einstellungen')">
                <form method="POST" action="{{ route('agile.settings.update', $project) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="lock_version" value="{{ $board->lock_version }}">
                    <x-form-group :legend="__('Einstellungen')" icon="tune" tone="ghost" cols="2" compact>
                        <div class="fieldset">
                            <label class="fieldset-label">{{ __('Name') }}</label>
                            <input name="name" required minlength="2" maxlength="120" class="input input-bordered w-full" value="{{ old('name', $board->name) }}">
                        </div>
                        <div class="fieldset">
                            <label class="fieldset-label">{{ __('Methode') }}</label>
                            <select name="method" class="select select-bordered w-full">
                                <option value="kanban" @selected($board->method === 'kanban')>{{ __('Kanban') }}</option>
                                <option value="scrum" @selected($board->method === 'scrum')>{{ __('Scrum') }}</option>
                            </select>
                        </div>
                        <div class="fieldset md:col-span-2">
                            <label class="fieldset-label">{{ __('Beschreibung') }}</label>
                            <input name="description" maxlength="500" class="input input-bordered w-full" value="{{ old('description', $board->description) }}">
                        </div>
                        <div class="fieldset md:col-span-2">
                            <label class="fieldset-label">{{ __('Definition of Done (eine Zeile je Punkt)') }}</label>
                            <textarea name="dod_items" rows="3" class="textarea textarea-bordered w-full">{{ old('dod_items', implode("\n", (array) ($board->dod_items ?? []))) }}</textarea>
                        </div>
                    </x-form-group>
                    <x-icon-btn icon="check" tone="primary" size="sm" type="submit" show-label>{{ __('Speichern') }}</x-icon-btn>
                </form>
            </x-card>

            {{-- Spalten-Verwaltung (P3): umbenennen/sortieren/Kategorie/WIP;
                 Löschen nur leerer Spalten, open/done bleiben abgedeckt. --}}
            <x-card :title="__('Spalten')">
                <div class="space-y-2">
                    @foreach ($board->columns as $column)
                        <div class="flex flex-wrap items-end gap-2 rounded border border-base-300 p-2">
                            <form method="POST" action="{{ route('agile.columns.update', [$project, $column]) }}" class="flex grow flex-wrap items-end gap-2">
                                @csrf @method('PATCH')
                                <div class="fieldset grow">
                                    <label class="fieldset-label">{{ __('Name') }}</label>
                                    <input name="name" required minlength="2" maxlength="80" class="input input-sm input-bordered w-full" value="{{ $column->name }}">
                                </div>
                                <div class="fieldset">
                                    <label class="fieldset-label">{{ __('Kategorie') }}</label>
                                    <select name="category" class="select select-sm select-bordered">
                                        @foreach (\App\Enums\Agile\AgileColumnCategory::cases() as $category)
                                            <option value="{{ $category->value }}" @selected($column->category === $category)>{{ $category->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="fieldset">
                                    <label class="fieldset-label"><x-term glossary="wip">{{ __('WIP-Limit') }}</x-term></label>
                                    <input name="wip_limit" type="number" min="1" max="99" class="input input-sm input-bordered w-20" value="{{ $column->wip_limit }}">
                                </div>
                                <div class="fieldset">
                                    <label class="fieldset-label">{{ __('Position') }}</label>
                                    <input name="position" type="number" min="1" max="50" class="input input-sm input-bordered w-20" value="{{ $column->position }}">
                                </div>
                                <div class="fieldset">
                                    <label class="fieldset-label">{{ __('Berichtsrolle') }}</label>
                                    <select name="report_role" class="select select-sm select-bordered">
                                        <option value="">{{ __('Unklassifiziert') }}</option>
                                        <option value="working" @selected($column->report_role === 'working')>{{ __('Arbeitszeit') }}</option>
                                        <option value="waiting" @selected($column->report_role === 'waiting')>{{ __('Wartezeit') }}</option>
                                    </select>
                                </div>
                                <x-icon-btn icon="check" tone="ghost" size="sm" type="submit" :label="__('Spalte speichern')" />
                            </form>
                            <form method="POST" action="{{ route('agile.columns.destroy', [$project, $column]) }}"
                                  onsubmit="return confirm('{{ __('Spalte wirklich löschen?') }}');">
                                @csrf @method('DELETE')
                                <x-icon-btn icon="delete" tone="ghost" size="sm" type="submit" :label="__('Spalte löschen')"
                                            :disabled="$column->workItems->isNotEmpty()" />
                            </form>
                        </div>
                    @endforeach
                </div>
                <form method="POST" action="{{ route('agile.columns.store', $project) }}" class="mt-3 flex flex-wrap items-end gap-2">
                    @csrf
                    <div class="fieldset grow">
                        <label class="fieldset-label">{{ __('Neue Spalte') }}</label>
                        <input name="name" required minlength="2" maxlength="80" class="input input-sm input-bordered w-full" placeholder="{{ __('Name') }}">
                    </div>
                    <select name="category" class="select select-sm select-bordered">
                        @foreach (\App\Enums\Agile\AgileColumnCategory::cases() as $category)
                            <option value="{{ $category->value }}" @selected($category === \App\Enums\Agile\AgileColumnCategory::InProgress)>{{ $category->label() }}</option>
                        @endforeach
                    </select>
                    <input name="wip_limit" type="number" min="1" max="99" placeholder="WIP" class="input input-sm input-bordered w-20">
                    <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('Hinzufügen') }}</x-icon-btn>
                </form>
            </x-card>
        @endif
    @endif
</x-page-shell>
@endsection
