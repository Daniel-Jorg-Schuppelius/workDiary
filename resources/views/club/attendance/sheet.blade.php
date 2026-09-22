{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : sheet.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Anwesenheitsliste je Vereinstermin (Feature 159, MVP-844): mobile
  Sammelerfassung (anwesend/teilweise/entschuldigt/abwesend + Minuten),
  Bestätigung mit Sperrzähler, Korrekturen mit Grund, spontane Ergänzung,
  bestätigte Versionen. Nicht bearbeitete Zeilen bleiben offen.
--}}
@extends('layouts.app')
@section('title', __('club.attendance.title.sheet'))
@section('nav-title', __('club.attendance.title.sheet'))
@section('content')
@php
    $start = $event->started_at->orgTz();
    $end = $event->ended_at->orgTz();
    $timeLabel = $start->format('d.m.Y H:i') . ' – ' . ($start->isSameDay($end) ? $end->format('H:i') : $end->format('d.m.Y H:i'));
    $isConfirmed = $sheet?->isConfirmed() ?? false;
    $editable = $canRecord && ! $isConfirmed;
@endphp
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$event->title . ' · ' . $timeLabel"
                        :badge="$sheet === null ? __('club.attendance.label.no_sheet') : ($isConfirmed ? __('club.attendance.label.confirmed') : __('club.attendance.label.open'))"
                        :badgeTone="$isConfirmed ? 'success' : 'warning'">
            <x-slot:actions>
                @if ($canRecord)
                    <x-icon-btn icon="person_add" tone="outline" size="sm"
                                data-entry-modal-trigger
                                :href="route('club.events.attendance.spontaneous.create', $event)"
                                show-label>{{ __('club.attendance.action.spontaneous') }}</x-icon-btn>
                    @if ($isConfirmed)
                        <x-icon-btn icon="lock_open" tone="outline" size="sm" class="btn-warning"
                                    data-entry-modal-trigger
                                    :href="route('club.events.attendance.reopen.edit', $event)"
                                    show-label>{{ __('club.attendance.action.reopen') }}</x-icon-btn>
                    @endif
                @endif
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                            :href="route('club.events.show', $event)"
                            show-label>{{ __('club.attendance.action.back_to_event') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($errors->any())
        <div class="alert alert-error text-sm" role="alert">
            <x-icon name="error" />
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($sheet === null)
        <x-empty-state framed icon="fact_check" :title="__('club.attendance.empty.no_sheet')" />
    @else
        <form method="POST" action="{{ route('club.events.attendance.save', $event) }}" id="attendance-sheet-form" class="space-y-4">
            @csrf
            <input type="hidden" name="version" value="{{ $sheet->version }}">

            <x-card :title="__('club.attendance.card.sheet')" icon="fact_check">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="fieldset">
                        <label class="fieldset-label" for="conducted-minutes">{{ __('club.attendance.field.conducted_minutes') }}</label>
                        <input id="conducted-minutes" name="conducted_minutes" type="number" min="0" max="1440"
                               class="input input-sm input-bordered w-full" value="{{ old('conducted_minutes', $sheet->conducted_minutes) }}"
                               @disabled(! $editable)>
                        <p class="text-xs text-muted">{{ __('club.attendance.hint.conducted', ['max' => $eventMinutes]) }}</p>
                    </div>
                    <div class="fieldset">
                        <span class="fieldset-label">{{ __('club.attendance.field.version') }}</span>
                        <span class="text-sm tabular-nums">{{ $sheet->version }}</span>
                        <p class="text-xs text-muted">{{ __('club.attendance.hint.version') }}</p>
                    </div>
                    <div class="fieldset">
                        <span class="fieldset-label">{{ __('club.attendance.field.confirmed') }}</span>
                        <span class="text-sm">{{ $sheet->confirmed_at ? $sheet->confirmed_at->orgTz()->format('d.m.Y H:i') . ' · ' . ($sheet->confirmedBy?->name ?? '–') : '–' }}</span>
                        @if ($isConfirmed && $sheet->changed_since_confirmation)
                            <p class="text-xs text-warning">{{ __('club.attendance.hint.changed_since_confirmation') }}</p>
                        @endif
                    </div>
                </div>
            </x-card>

            <x-card :title="__('club.attendance.card.roster')" icon="groups" :count="$roster->count()">
                <p class="mb-2 text-xs text-muted">{{ __('club.attendance.hint.roster') }}</p>
                <x-table :bare="true" size="sm">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('club.field.member') }}</th>
                            <th>{{ __('club.field.status') }}</th>
                            <th class="w-28">{{ __('club.attendance.field.minutes') }}</th>
                            <th class="w-40">{{ __('club.attendance.field.arrived_left') }}</th>
                            <th></th>
                        </tr>
                    </x-slot:head>
                    @forelse ($roster as $member)
                        @php
                            $record = $records->get($member->id);
                            $key = $member->sqid;
                            $currentStatus = old("records.$key.status", $record?->status->value);
                        @endphp
                        <tr class="align-top">
                            <td class="font-medium">
                                {{ $member->fullName() }}
                                <span class="font-mono text-xs text-muted">{{ $member->displayNo() }}</span>
                                @if ($record?->spontaneous)
                                    <x-status-badge tone="info" size="xs" :label="__('club.attendance.label.spontaneous')" />
                                @endif
                                @if ($record?->hasUnresolvedOverlap())
                                    <x-status-badge tone="warning" size="xs" :label="__('club.attendance.label.overlap')" />
                                @endif
                                @if ($record === null)
                                    <span class="block text-xs text-muted">{{ __('club.attendance.label.not_recorded') }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($editable)
                                    <div class="join" role="group" aria-label="{{ __('club.field.status') }}">
                                        @foreach ($statuses as $status)
                                            <label class="btn btn-xs join-item has-[:checked]:btn-primary" title="{{ $status->label() }}">
                                                <input type="radio" class="sr-only" id="st-{{ $key }}-{{ $status->value }}"
                                                       name="records[{{ $key }}][status]" value="{{ $status->value }}"
                                                       @checked($currentStatus === $status->value)>
                                                <x-icon :name="$status->icon()" />
                                                <span class="hidden sm:inline">{{ $status->label() }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                @elseif ($record)
                                    <x-status-badge :tone="$record->status->tone()" size="sm" :icon="$record->status->icon()">{{ $record->status->label() }}</x-status-badge>
                                @else
                                    <span class="text-xs text-muted">{{ __('club.attendance.label.open_row') }}</span>
                                @endif
                            </td>
                            <td class="tabular-nums">
                                @if ($editable)
                                    <label for="min-{{ $key }}" class="sr-only">{{ __('club.attendance.field.minutes') }}</label>
                                    <input id="min-{{ $key }}" type="number" min="0" max="{{ $maxMinutes }}" name="records[{{ $key }}][minutes]"
                                           class="input input-xs input-bordered w-24" placeholder="{{ $maxMinutes }}"
                                           value="{{ old("records.$key.minutes", $record?->minutes) }}">
                                @else
                                    {{ $record?->minutes ?? '–' }}
                                @endif
                            </td>
                            <td class="text-xs">
                                @if ($editable)
                                    <div class="flex gap-1">
                                        <label for="arr-{{ $key }}" class="sr-only">{{ __('club.attendance.field.arrived_at') }}</label>
                                        <input id="arr-{{ $key }}" type="time" name="records[{{ $key }}][arrived_at]" class="input input-xs input-bordered w-20"
                                               value="{{ old("records.$key.arrived_at", $record?->arrived_at?->orgTz()->format('H:i')) }}">
                                        <label for="lft-{{ $key }}" class="sr-only">{{ __('club.attendance.field.left_at') }}</label>
                                        <input id="lft-{{ $key }}" type="time" name="records[{{ $key }}][left_at]" class="input input-xs input-bordered w-20"
                                               value="{{ old("records.$key.left_at", $record?->left_at?->orgTz()->format('H:i')) }}">
                                    </div>
                                @else
                                    {{ $record?->arrived_at ? $record->arrived_at->orgTz()->format('H:i') . ' – ' . ($record->left_at?->orgTz()->format('H:i') ?? '…') : '–' }}
                                @endif
                            </td>
                            <td class="text-right">
                                @if ($record && $canRecord)
                                    <div class="flex justify-end gap-1">
                                        @if ($record->revisions->isNotEmpty())
                                            <span class="badge badge-ghost badge-xs" title="{{ __('club.attendance.label.revisions') }}">{{ $record->revisions->count() }}</span>
                                        @endif
                                        <x-icon-btn icon="edit" tone="outline" size="xs"
                                                    data-entry-modal-trigger
                                                    :href="route('club.events.attendance.records.edit', [$event, $record])"
                                                    :label="__('club.attendance.action.correct')" />
                                        @if ($record->hasUnresolvedOverlap())
                                            <x-icon-btn icon="rule" tone="outline" size="xs" class="btn-warning"
                                                        data-entry-modal-trigger
                                                        :href="route('club.events.attendance.records.overlap.edit', [$event, $record])"
                                                        :label="__('club.attendance.action.clear_overlap')" />
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-table.empty icon="groups" :colspan="5" :title="__('club.attendance.empty.roster')" compact />
                    @endforelse
                </x-table>
                @if ($editable)
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <x-icon-btn type="submit" icon="save" tone="outline" size="sm" show-label form="attendance-sheet-form">{{ __('club.attendance.action.save') }}</x-icon-btn>
                        <p class="text-xs text-muted">{{ __('club.attendance.hint.save') }}</p>
                    </div>
                @endif
            </x-card>
        </form>

        @if ($canRecord && ! $isConfirmed)
            <x-card :title="__('club.attendance.card.confirm')" icon="verified">
                <p class="mb-3 text-sm text-muted">{{ __('club.attendance.hint.confirm') }}</p>
                <x-action-form :action="route('club.events.attendance.confirm', $event)" :confirm="__('club.attendance.confirm.confirm')" confirm-icon="verified" confirm-tone="primary">
                    <input type="hidden" name="version" value="{{ $sheet->version }}">
                    <x-icon-btn type="submit" icon="verified" tone="primary" size="sm" show-label>{{ __('club.attendance.action.confirm') }}</x-icon-btn>
                </x-action-form>
            </x-card>
        @endif

        @if ($confirmations->isNotEmpty())
            <x-card :title="__('club.attendance.card.versions')" icon="history" :count="$confirmations->count()">
                <ul class="space-y-1 text-sm">
                    @foreach ($confirmations as $confirmation)
                        <li class="flex flex-wrap items-center gap-2">
                            <span class="badge badge-ghost badge-sm">v{{ $confirmation->version }}</span>
                            <span>{{ $confirmation->confirmed_at->orgTz()->format('d.m.Y H:i') }}</span>
                            <span class="text-muted">· {{ $confirmation->confirmedBy?->name ?? '–' }}</span>
                            <span class="text-muted">· {{ trans_choice('club.attendance.label.snapshot_rows', count($confirmation->snapshot ?? []), ['count' => count($confirmation->snapshot ?? [])]) }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif
    @endif
</x-page-shell>
@endsection
