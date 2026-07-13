{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : backlog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Produkt-Backlog (Feature 064, MVP-140): Rangliste mit Filtern,
     Hoch/Runter-Aktionen (A11y — Drag dockt später am selben Endpoint an),
     Punkte/Typ und Akzeptanzkriterien. Bewusst „Produkt-Backlog" (die
     Diary-Vokabel „Backlog" meint den Scheduling-Modus). --}}

@extends('layouts.app')

@section('title', __('Produkt-Backlog') . ' — ' . $project->name)
@section('nav-title', __('Produkt-Backlog'))

@section('content')
<x-page-shell>
    <x-page-toolbar>
        <x-slot:title>{{ __('Produkt-Backlog') }} — {{ $project->name }}</x-slot:title>
        <x-slot:subtitle>{{ __('Priorisierte Arbeitselemente des Projektboards (:method).', ['method' => $board->method === 'scrum' ? 'Scrum' : 'Kanban']) }}</x-slot:subtitle>
        <x-slot:actions>
            <x-icon-btn icon="view_kanban" tone="ghost" size="sm" :href="route('agile.board', $project)" show-label>{{ __('Zum Board') }}</x-icon-btn>
        </x-slot:actions>
    </x-page-toolbar>

    <x-filter-bar :action="route('agile.backlog', $project)" :reset="route('agile.backlog', $project)">
        <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('Suche…') }}"
               class="input input-sm input-bordered w-48 shrink-0" aria-label="{{ __('Suche') }}">
        <select name="type" class="select select-sm select-bordered w-40 shrink-0" aria-label="{{ __('Typ') }}">
            <option value="">{{ __('Alle Typen') }}</option>
            @foreach (\App\Enums\Agile\AgileItemType::cases() as $type)
                <option value="{{ $type->value }}" @selected($filters['type'] === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
        <label class="label cursor-pointer gap-2 shrink-0">
            <input type="checkbox" name="blocked" value="1" class="checkbox checkbox-sm" @checked($filters['blocked'] === '1')>
            <span class="label-text text-sm">{{ __('Nur blockierte') }}</span>
        </label>
    </x-filter-bar>

    @if ($canManage)
        <x-card :title="__('Neues Arbeitselement')">
            <form method="POST" action="{{ route('agile.items.store', $project) }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <div class="fieldset grow">
                    <label class="fieldset-label">{{ __('Titel') }}</label>
                    <input name="title" required minlength="2" maxlength="255" class="input input-sm input-bordered w-full">
                </div>
                <select name="item_type" class="select select-sm select-bordered">
                    @foreach (\App\Enums\Agile\AgileItemType::cases() as $type)
                        <option value="{{ $type->value }}" @selected($type === \App\Enums\Agile\AgileItemType::Story)>{{ $type->label() }}</option>
                    @endforeach
                </select>
                <input name="story_points" type="number" min="1" max="999" placeholder="SP" class="input input-sm input-bordered w-20">
                <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('Anlegen') }}</x-icon-btn>
            </form>
            @if ($adoptableTasks->isNotEmpty())
                <form method="POST" action="{{ route('agile.items.adopt', $project) }}" class="mt-2 flex items-center gap-2">
                    @csrf
                    <select name="task_id" class="select select-sm select-bordered flex-1">
                        @foreach ($adoptableTasks as $task)
                            <option value="{{ $task->sqid }}">{{ $task->title }}</option>
                        @endforeach
                    </select>
                    <x-icon-btn icon="move_to_inbox" tone="outline" size="sm" type="submit" show-label>{{ __('Aufgabe übernehmen') }}</x-icon-btn>
                </form>
            @endif
        </x-card>
    @endif

    <x-card :title="__('Rangliste')">
        @if ($items->isEmpty())
            <x-empty-state icon="low_priority" :title="__('Das Produkt-Backlog ist leer.')" compact />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th class="w-20">{{ __('Rang') }}</th>
                        <th>{{ __('Titel') }}</th>
                        <th>{{ __('Typ') }}</th>
                        <th class="text-right"><x-term glossary="story_points">SP</x-term></th>
                        <th>{{ __('Spalte') }}</th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @foreach ($items as $index => $item)
                    <tr @if ($item->isBlocked()) class="bg-error/5" @endif>
                        <td class="tabular-nums text-sm text-base-content/60">{{ $index + 1 }}</td>
                        <td>
                            {{ $item->task?->title ?? '—' }}
                            @if ($item->isBlocked())
                                <x-status-badge tone="error" size="xs">{{ __('blockiert') }}</x-status-badge>
                            @endif
                        </td>
                        <td><x-status-badge tone="neutral" size="xs">{{ $item->item_type->label() }}</x-status-badge></td>
                        <td class="text-right tabular-nums">{{ $item->story_points ?? '—' }}</td>
                        <td class="text-sm text-base-content/60">{{ $item->column?->name ?? __('Produkt-Backlog') }}</td>
                        <td class="text-right">
                            @if ($canPrioritize)
                                <div class="flex justify-end gap-1">
                                    @if ($index > 0)
                                        <form method="POST" action="{{ route('agile.items.rerank', [$project, $item]) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="lock_version" value="{{ $item->lock_version }}">
                                            <input type="hidden" name="after" value="{{ $index > 1 ? $items[$index - 2]->sqid : '' }}">
                                            <x-icon-btn icon="arrow_upward" tone="ghost" size="xs" type="submit" :label="__('Nach oben')" />
                                        </form>
                                    @endif
                                    @if ($index < $items->count() - 1)
                                        <form method="POST" action="{{ route('agile.items.rerank', [$project, $item]) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="lock_version" value="{{ $item->lock_version }}">
                                            <input type="hidden" name="after" value="{{ $items[$index + 1]->sqid }}">
                                            <x-icon-btn icon="arrow_downward" tone="ghost" size="xs" type="submit" :label="__('Nach unten')" />
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection
