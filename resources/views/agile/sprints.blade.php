{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : sprints.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Sprints (Feature 064, MVP-142): Planung, Zuordnung, Start/Abschluss/
     Abbruch. Abschluss verlangt je unerledigtem Element eine explizite
     Entscheidung (Backlog oder geplanter Folgesprint, kein Default). --}}

@extends('layouts.app')

@section('title', __('Sprints') . ' — ' . $project->name)
@section('nav-title', __('Sprints'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:title>{{ $project->name }}</x-slot:title>
            <x-slot:subtitle>{{ __('Sprint-Lebenszyklus des Projektboards (:method).', ['method' => $board->method === 'scrum' ? 'Scrum' : 'Kanban']) }}</x-slot:subtitle>
            <x-slot:actions>
                <x-icon-btn icon="view_kanban" tone="ghost" size="sm" :href="route('agile.board', $project)" show-label>{{ __('Zum Board') }}</x-icon-btn>
                <x-icon-btn icon="low_priority" tone="ghost" size="sm" :href="route('agile.backlog', $project)" show-label>{{ __('Produkt-Backlog') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($canManage)
        <x-card :title="__('Sprint planen')">
            <form method="POST" action="{{ route('agile.sprints.store', $project) }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <div class="fieldset grow">
                    <label class="fieldset-label">{{ __('Name') }}</label>
                    <input name="name" required minlength="2" maxlength="120" class="input input-sm input-bordered w-full" placeholder="{{ __('Sprint 1') }}">
                </div>
                <div class="fieldset grow">
                    <label class="fieldset-label">{{ __('Ziel (Pflicht zum Start)') }}</label>
                    <input name="goal" maxlength="500" class="input input-sm input-bordered w-full">
                </div>
                <x-date-range from-name="starts_on" to-name="ends_on" size="sm" />
                <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('Planen') }}</x-icon-btn>
            </form>
        </x-card>
    @endif

    @if ($sprints->isEmpty())
        <x-empty-state icon="sprint" framed :title="__('Noch keine Sprints geplant.')" />
    @else
        @foreach ($sprints as $sprint)
            <x-card>
                @php([$statusTone, $statusLabel] = match ($sprint->status) {
                    'active' => ['success', __('aktiv')],
                    'completed' => ['info', __('abgeschlossen')],
                    'cancelled' => ['error', __('abgebrochen')],
                    default => ['neutral', __('geplant')],
                })
                <x-slot:title>
                    {{ $sprint->name }}
                    <x-status-badge :tone="$statusTone" size="xs">{{ $statusLabel }}</x-status-badge>
                </x-slot:title>

                <p class="text-sm text-base-content/70">
                    @if ($sprint->goal){{ __('Ziel:') }} {{ $sprint->goal }} · @endif
                    @if ($sprint->starts_on && $sprint->ends_on)
                        {{ $sprint->starts_on->isoFormat('L') }} – {{ $sprint->ends_on->isoFormat('L') }}
                    @endif
                </p>

                @if ($sprint->items->isEmpty())
                    <p class="mt-2 text-xs text-base-content/50">{{ __('Keine Elemente zugeordnet.') }}</p>
                @else
                    <ul class="mt-2 space-y-1">
                        @foreach ($sprint->items as $assignment)
                            @php($workItem = $assignment->workItem)
                            <li class="flex items-center justify-between gap-2 rounded border border-base-300 px-2 py-1 text-sm">
                                <span>
                                    {{ $workItem?->task?->title ?? '—' }}
                                    @if ($workItem?->story_points !== null)
                                        <span class="badge badge-ghost badge-xs">{{ $workItem->story_points }} SP</span>
                                    @endif
                                    @if ($assignment->added_after_start)
                                        <x-status-badge tone="warning" size="xs">{{ __('nach Start') }}</x-status-badge>
                                    @endif
                                    @if ($workItem?->column?->category?->value === 'done')
                                        <x-status-badge tone="success" size="xs">{{ __('erledigt') }}</x-status-badge>
                                    @endif
                                </span>
                                @if ($canManage && ! $sprint->isFinished() && $workItem !== null)
                                    <form method="POST" action="{{ route('agile.sprints.items.remove', [$project, $sprint, $workItem]) }}">
                                        @csrf @method('DELETE')
                                        <x-icon-btn icon="close" tone="ghost" size="xs" type="submit" :label="__('Entfernen')" />
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($canManage && ! $sprint->isFinished())
                    <div class="mt-3 flex flex-wrap items-end gap-2">
                        <form method="POST" action="{{ route('agile.sprints.items.assign', [$project, $sprint]) }}" class="flex items-center gap-2">
                            @csrf
                            <select name="item" class="select select-sm select-bordered" aria-label="{{ __('Element zuordnen') }}">
                                @foreach ($assignableItems as $candidate)
                                    <option value="{{ $candidate->sqid }}">{{ $candidate->task?->title ?? '—' }}</option>
                                @endforeach
                            </select>
                            <x-icon-btn icon="playlist_add" tone="outline" size="sm" type="submit" show-label>{{ __('Zuordnen') }}</x-icon-btn>
                        </form>

                        @if ($sprint->status === 'planned')
                            <form method="POST" action="{{ route('agile.sprints.start', [$project, $sprint]) }}" class="flex items-end gap-1">
                                @csrf
                                {{-- Kapazitätskorrektur (P10): optional, Begründung Pflicht. --}}
                                <input name="capacity_adjustment_hours" type="number" step="0.5" min="-999" max="999"
                                       placeholder="±h" class="input input-sm input-bordered w-20"
                                       aria-label="{{ __('Kapazitätskorrektur (Stunden)') }}">
                                <input name="capacity_adjustment_reason" maxlength="300"
                                       placeholder="{{ __('Korrektur-Begründung') }}" class="input input-sm input-bordered w-40">
                                <x-icon-btn icon="play_arrow" tone="primary" size="sm" type="submit" show-label>{{ __('Starten') }}</x-icon-btn>
                            </form>
                        @endif

                        <details>
                            <summary class="cursor-pointer text-xs text-base-content/60">{{ __('Abbrechen…') }}</summary>
                            <form method="POST" action="{{ route('agile.sprints.cancel', [$project, $sprint]) }}" class="mt-1 flex items-center gap-1">
                                @csrf
                                <input name="reason" required minlength="3" maxlength="300" placeholder="{{ __('Grund (Pflicht)') }}" class="input input-xs input-bordered">
                                <x-icon-btn icon="cancel" tone="error" size="xs" type="submit" :label="__('Sprint abbrechen')" />
                            </form>
                        </details>
                    </div>
                @endif

                @if ($canManage && $sprint->isActive())
                    @php($openAssignments = $sprint->items->filter(fn($a) => $a->workItem?->column?->category?->value !== 'done'))
                    <form method="POST" action="{{ route('agile.sprints.complete', [$project, $sprint]) }}" class="mt-3 rounded border border-base-300 p-2">
                        @csrf
                        <p class="mb-1 text-sm font-semibold">{{ __('Sprint abschließen') }}</p>
                        @if ($openAssignments->isEmpty())
                            <p class="text-xs text-base-content/60">{{ __('Alle Elemente sind erledigt.') }}</p>
                        @else
                            <p class="mb-1 text-xs text-base-content/60">{{ __('Je unerledigtem Element eine Entscheidung treffen (Pflicht, kein Standard):') }}</p>
                            <div class="space-y-1">
                                @foreach ($openAssignments as $assignment)
                                    <label class="flex items-center gap-2 text-sm">
                                        <span class="grow">{{ $assignment->workItem?->task?->title ?? '—' }}</span>
                                        <select name="decisions[{{ $assignment->work_item_id }}]" required class="select select-xs select-bordered">
                                            <option value="">{{ __('Bitte wählen…') }}</option>
                                            <option value="backlog">{{ __('Zurück ins Produkt-Backlog') }}</option>
                                            @foreach ($sprints->where('status', 'planned') as $followUp)
                                                <option value="{{ $followUp->sqid }}">{{ __('In Sprint :name', ['name' => $followUp->name]) }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                        <x-icon-btn icon="flag" tone="primary" size="sm" type="submit" show-label class="mt-2">{{ __('Abschließen') }}</x-icon-btn>
                    </form>
                @endif

                @if ($sprint->completion_snapshot !== null)
                    <p class="mt-2 text-xs text-base-content/60">
                        {{ __(':done von :committed Punkten erledigt, :open Elemente offen übergeben.', [
                            'done' => $sprint->completion_snapshot['done_points'] ?? 0,
                            'committed' => $sprint->completion_snapshot['committed_points'] ?? 0,
                            'open' => $sprint->completion_snapshot['open_items'] ?? 0,
                        ]) }}
                    </p>
                @endif
            </x-card>
        @endforeach
    @endif
</x-page-shell>
@endsection
