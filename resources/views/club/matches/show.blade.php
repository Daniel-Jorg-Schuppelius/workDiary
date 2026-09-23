{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Detailseite Spieltag (Feature 159, MVP-852): Verfügbarkeit (Zusage ≠ Nominierung), Aufstellung im Format des
  Sportartenprofils mit Freigabe und Konfliktprüfung, Terminrollen, Ergebnis; Details rechts. Variablen:
  $event, $match, $details, $profile, $candidates, $conflicts, $roles, $lineupEntries, $isCancelled,
  $canManage, $canEdit, $slots, $availabilityOptions.
--}}
@extends('layouts.app')
@section('title', $event->title)
@section('nav-title', $event->title)
@section('content')
@php
    $start = $event->started_at->orgTz();
    $end = $event->ended_at->orgTz();
    $timeLabel = $start->format('d.m.Y H:i') . ' – ' . ($start->isSameDay($end) ? $end->format('H:i') : $end->format('d.m.Y H:i'));
    $isRacket = $profile?->hasPairings() ?? false;
    $positions = $profile?->positions ?? [];
    $released = $match->isReleased();
    $counts = ['field' => 0, 'bench' => 0, 'persons' => $lineupEntries->pluck('club_member_id')->unique()->count()];
    foreach ($lineupEntries as $entry) { if (isset($counts[$entry->slot->value])) { $counts[$entry->slot->value]++; } }
@endphp
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$timeLabel . ' · ' . ($match->is_home ? __('club.matches.label.home') : __('club.matches.label.away')) . ($match->venue ? ' · ' . $match->venue : ($event->rooms->isNotEmpty() ? ' · ' . $event->rooms->pluck('name')->implode(', ') : ''))"
                        :badge="$isCancelled ? __('club.events.label.cancelled') : ($match->result_summary ?? $match->lineup_status->label())"
                        :badgeTone="$isCancelled ? 'error' : ($match->result_summary ? 'primary' : $match->lineup_status->tone())">
            <x-slot:actions>
                @if ($canManage && ! $isCancelled)
                    <x-icon-btn icon="scoreboard" tone="primary" size="sm" data-entry-modal-trigger :href="route('club.matches.result.edit', $event)" show-label>{{ __('club.matches.action.record_result') }}</x-icon-btn>
                    <x-icon-btn icon="sports" tone="outline" size="sm" data-entry-modal-trigger :href="route('club.matches.roles.create', $event)" show-label>{{ __('club.matches.action.add_role') }}</x-icon-btn>
                    <x-icon-btn icon="fact_check" tone="outline" size="sm" :href="route('club.events.attendance.show', $event)" show-label>{{ __('club.attendance.action.open') }}</x-icon-btn>
                @endif
                @if ($canEdit)
                    <x-icon-btn icon="edit" tone="outline" size="sm" data-entry-modal-trigger :href="route('club.matches.edit', $event)" show-label>{{ __('club.action.edit') }}</x-icon-btn>
                    @unless ($isCancelled)
                        <x-icon-btn icon="event_busy" tone="outline" size="sm" class="btn-warning" data-entry-modal-trigger :href="route('club.events.cancel.edit', $event)" show-label>{{ __('club.events.action.cancel_event') }}</x-icon-btn>
                    @endunless
                @endif
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('club.matches.index')" show-label>{{ __('club.action.back') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- Aufstellung: Kandidaten (Kader + Gruppe) mit Verfügbarkeit; Platz, Position, Trikot, Paarung je Zeile. --}}
            <x-card :title="__('club.matches.card.lineup')" icon="format_list_numbered" :count="$counts['persons']">
                <div class="mb-2 flex flex-wrap items-center gap-2 text-xs">
                    <x-status-badge :tone="$match->lineup_status->tone()" size="xs">{{ $match->lineup_status->label() }}</x-status-badge>
                    @if ($released && $match->lineup_released_at)
                        <span class="text-muted">{{ __('club.matches.label.released_at', ['when' => $match->lineup_released_at->orgTz()->format('d.m.Y H:i'), 'name' => $match->lineupReleasedBy?->name ?? '–']) }}</span>
                    @endif
                    @if ($profile)
                        <span class="text-muted">{{ __('club.matches.label.sizes', ['field' => $counts['field'], 'max_field' => $profile->squad_size_field ?? '∞', 'bench' => $counts['bench'], 'max_bench' => $profile->squad_size_bench ?? '∞']) }}</span>
                    @endif
                    @if ($match->lineup_conflict_note)
                        <span class="text-warning">{{ __('club.matches.label.override_note', ['note' => $match->lineup_conflict_note]) }}</span>
                    @endif
                </div>
                @if ($errors->has('lineup'))
                    <div class="alert alert-warning mb-2 text-sm" role="alert">
                        <x-icon name="warning" />
                        <ul class="list-disc pl-4">@foreach ($errors->get('lineup') as $message)<li>{{ $message }}</li>@endforeach</ul>
                    </div>
                @endif
                @if ($conflicts !== [] && ! $errors->has('lineup'))
                    <div class="alert alert-warning mb-2 text-sm" role="status">
                        <x-icon name="warning" />
                        <div>
                            <p class="font-medium">{{ __('club.matches.label.conflicts') }}</p>
                            <ul class="list-disc pl-4">
                                @foreach ($conflicts as $conflict)
                                    <li>{{ $conflict['reason'] === 'unavailable' ? __('club.matches.conflict.unavailable', ['name' => $conflict['member']->fullName()]) : __('club.matches.conflict.overlap', ['name' => $conflict['member']->fullName(), 'title' => $conflict['event']?->title ?? '']) }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif
                <p class="mb-2 text-xs text-muted">{{ __('club.matches.hint.lineup') }}</p>
                @if ($canManage && ! $isCancelled)
                    <form method="POST" action="{{ route('club.matches.lineup', $event) }}" data-entry-form>
                        @csrf
                        <x-table :bare="true" size="sm">
                            <x-slot:head>
                                <tr>
                                    <th>{{ __('club.field.member') }}</th>
                                    <th>{{ __('club.matches.field.availability') }}</th>
                                    <th>{{ __('club.matches.field.slot') }}</th>
                                    @if ($isRacket)<th class="w-20">{{ __('club.matches.field.pairing_no') }}</th>@endif
                                    <th>{{ __('club.teams.field.position') }}</th>
                                    <th class="w-20">{{ __('club.teams.field.jersey_no') }}</th>
                                    <th class="w-16">{{ __('club.matches.field.order_no') }}</th>
                                </tr>
                            </x-slot:head>
                            @forelse ($candidates as $index => $row)
                                @php
                                    $member = $row['member'];
                                    $squad = $row['squad'];
                                    $availability = $row['availability'];
                                    $entry = $row['entries']->first();
                                    $sqid = $member->sqid;
                                @endphp
                                <tr class="align-top">
                                    <td class="text-sm">
                                        <input type="hidden" name="lineup_member[{{ $index }}]" value="{{ $sqid }}">
                                        <a href="{{ route('club.members.show', $member) }}" class="link link-hover font-medium">{{ $member->fullName() }}</a>
                                        @if ($squad?->isGuest())<span class="badge badge-ghost badge-xs">{{ __('club.teams.label.guest', ['origin' => $squad->guest_origin]) }}</span>@endif
                                        @if ($squad?->strength_rank)<span class="block text-xs text-muted">{{ __('club.teams.label.rank', ['rank' => $squad->strength_rank]) }}</span>@endif
                                    </td>
                                    <td>
                                        @if ($availability)
                                            <x-status-badge :tone="$availability->status->tone()" size="xs" :icon="$availability->status->icon()">{{ $availability->status->label() }}</x-status-badge>
                                            @if ($availability->note)<span class="block text-xs text-muted">{{ $availability->note }}</span>@endif
                                        @else
                                            <span class="text-xs text-muted">{{ __('club.matches.label.no_answer') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <select name="lineup_slot[{{ $index }}]" class="select select-bordered select-xs w-28" aria-label="{{ __('club.matches.field.slot') }}">
                                            <option value="">–</option>
                                            @foreach ($slots as $slot)
                                                <option value="{{ $slot->value }}" @selected(old('lineup_slot.' . $index, $entry?->slot->value) === $slot->value)>{{ $slot->label() }}</option>
                                            @endforeach
                                        </select>
                                        @if ($row['entries']->count() > 1)
                                            <span class="block text-xs text-muted">{{ $row['entries']->map(fn ($e) => $e->slot->label() . ($e->pairing_no ? ' ' . $e->pairing_no : ''))->implode(' + ') }}</span>
                                        @endif
                                    </td>
                                    @if ($isRacket)
                                        <td><input type="number" name="lineup_pairing[{{ $index }}]" min="1" max="20" class="input input-bordered input-xs w-16" value="{{ old('lineup_pairing.' . $index, $entry?->pairing_no) }}" aria-label="{{ __('club.matches.field.pairing_no') }}"></td>
                                    @endif
                                    <td>
                                        @if ($positions !== [])
                                            <select name="lineup_position[{{ $index }}]" class="select select-bordered select-xs w-32" aria-label="{{ __('club.teams.field.position') }}">
                                                <option value="">–</option>
                                                @foreach ($positions as $position)
                                                    <option value="{{ $position['code'] }}" @selected(old('lineup_position.' . $index, $entry?->position_code ?? $squad?->position_code) === $position['code'])>{{ $position['label'] }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input type="text" name="lineup_position[{{ $index }}]" maxlength="30" class="input input-bordered input-xs w-28" value="{{ old('lineup_position.' . $index, $entry?->position_code ?? $squad?->position_code) }}" aria-label="{{ __('club.teams.field.position') }}">
                                        @endif
                                    </td>
                                    <td><input type="number" name="lineup_jersey[{{ $index }}]" min="0" max="999" class="input input-bordered input-xs w-16" value="{{ old('lineup_jersey.' . $index, $entry?->jersey_no ?? $squad?->jersey_no) }}" aria-label="{{ __('club.teams.field.jersey_no') }}"></td>
                                    <td><input type="number" name="lineup_order[{{ $index }}]" min="0" max="999" class="input input-bordered input-xs w-14" value="{{ old('lineup_order.' . $index, $entry?->order_no) }}" aria-label="{{ __('club.matches.field.order_no') }}"></td>
                                </tr>
                            @empty
                                <x-table.empty icon="groups" :colspan="$isRacket ? 7 : 6" :title="__('club.matches.empty.candidates')" :message="__('club.matches.hint.candidates_empty')" compact />
                            @endforelse
                        </x-table>
                        @if ($candidates->isNotEmpty())
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <x-icon-btn type="submit" icon="save" tone="outline" size="sm" show-label>{{ __('club.matches.action.save_lineup') }}</x-icon-btn>
                                <x-icon-btn type="submit" name="release" value="1" icon="verified" tone="primary" size="sm" show-label>{{ __('club.matches.action.save_and_release') }}</x-icon-btn>
                            </div>
                        @endif
                    </form>
                    @if ($lineupEntries->isNotEmpty())
                        <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-base-300 pt-3">
                            @if ($released)
                                <x-action-form :action="route('club.matches.lineup.withdraw', $event)" :confirm="__('club.matches.confirm.withdraw')" confirm-icon="undo" confirm-tone="warning">
                                    <x-icon-btn type="submit" icon="undo" tone="outline" size="xs" class="btn-warning" show-label>{{ __('club.matches.action.withdraw_lineup') }}</x-icon-btn>
                                </x-action-form>
                            @elseif ($conflicts !== [])
                                <form method="POST" action="{{ route('club.matches.lineup.release', $event) }}" class="flex flex-wrap items-end gap-2" data-entry-form>
                                    @csrf
                                    <input type="hidden" name="override" value="1">
                                    <label class="form-control">
                                        <span class="label-text text-xs">{{ __('club.matches.field.override_note') }}</span>
                                        <input type="text" name="note" maxlength="255" required class="input input-bordered input-sm w-72" value="{{ old('note') }}">
                                    </label>
                                    <x-icon-btn type="submit" icon="verified" tone="outline" size="sm" class="btn-warning" show-label>{{ __('club.matches.action.release_despite_conflicts') }}</x-icon-btn>
                                </form>
                            @endif
                        </div>
                    @endif
                @else
                    <x-table :bare="true" size="sm">
                        <x-slot:head>
                            <tr><th>{{ __('club.field.member') }}</th><th>{{ __('club.matches.field.slot') }}</th><th>{{ __('club.teams.field.position') }}</th><th class="text-center">{{ __('club.teams.field.jersey_no') }}</th></tr>
                        </x-slot:head>
                        @forelse ($lineupEntries as $entry)
                            <tr>
                                <td class="text-sm">{{ $entry->member?->fullName() }}</td>
                                <td class="text-sm">{{ $entry->slot->label() }}@if ($entry->pairing_no) {{ $entry->pairing_no }}@endif</td>
                                <td class="text-sm">{{ $profile?->positionLabel($entry->position_code) ?? $entry->position_code ?? '–' }}</td>
                                <td class="text-center text-sm tabular-nums">{{ $entry->jersey_no ?? '–' }}</td>
                            </tr>
                        @empty
                            <x-table.empty icon="format_list_numbered" :colspan="4" :title="__('club.matches.empty.lineup')" compact />
                        @endforelse
                    </x-table>
                @endif
            </x-card>

            {{-- Verfügbarkeit durch die Leitung setzen (Mitglieder antworten im Portal). --}}
            @if ($canManage && ! $isCancelled && $candidates->isNotEmpty())
                <x-card :title="__('club.matches.card.availability')" icon="how_to_reg">
                    <p class="mb-2 text-xs text-muted">{{ __('club.matches.hint.availability') }}</p>
                    <form method="POST" action="{{ route('club.matches.availability', $event) }}" class="flex flex-wrap items-end gap-2" data-entry-form>
                        @csrf
                        <label class="form-control">
                            <span class="label-text text-xs">{{ __('club.field.member') }}</span>
                            <select name="club_member_id" class="select select-bordered select-sm w-56" required>
                                @foreach ($candidates as $row)
                                    <option value="{{ $row['member']->sqid }}">{{ $row['member']->fullName() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="form-control">
                            <span class="label-text text-xs">{{ __('club.matches.field.availability') }}</span>
                            <select name="status" class="select select-bordered select-sm w-40" required>
                                @foreach ($availabilityOptions as $option)
                                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="form-control">
                            <span class="label-text text-xs">{{ __('club.field.notes') }}</span>
                            <input type="text" name="note" maxlength="255" class="input input-bordered input-sm w-56">
                        </label>
                        <x-icon-btn type="submit" icon="save" tone="outline" size="sm" show-label>{{ __('club.action.save') }}</x-icon-btn>
                    </form>
                </x-card>
            @endif
        </div>

        <div class="space-y-4">
            <x-card :title="__('club.matches.card.match')" icon="info">
                <x-detail-grid>
                    <x-detail-grid.row :label="__('club.teams.field.team')">
                        <a href="{{ route('club.groups.show', $match->team) }}" class="link link-hover">{{ $match->team->name }}</a>
                        @if ($match->team->age_class) <span class="badge badge-ghost badge-xs">{{ $match->team->age_class }}</span>@endif
                    </x-detail-grid.row>
                    <x-detail-grid.row :label="__('club.matches.field.opponent')" :value="$match->opponent_name" />
                    <x-detail-grid.row :label="__('club.matches.field.competition')" :value="$match->competition ?? '–'" />
                    <x-detail-grid.row :label="__('club.teams.field.season')" :value="$match->season?->name ?? '–'" />
                    <x-detail-grid.row :label="__('club.matches.field.is_home')" :value="$match->is_home ? __('club.matches.label.home') : __('club.matches.label.away')" />
                    <x-detail-grid.row :label="__('club.matches.field.venue')" :value="$match->venue ?? ($event->rooms->pluck('name')->implode(', ') ?: '–')" />
                    <x-detail-grid.row :label="__('club.matches.field.meet_at')" :value="$match->meet_at?->orgTz()->format('d.m.Y H:i') ?? '–'" />
                    <x-detail-grid.row :label="__('club.events.field.leader')" :value="$event->responsibleUser?->name ?? '–'" />
                    <x-detail-grid.row :label="__('club.teams.field.profile')" :value="$profile?->name ?? __('club.teams.label.no_profile')" />
                    @if ($event->description)
                        <x-detail-grid.row :label="__('club.field.description')" :value="$event->description" />
                    @endif
                </x-detail-grid>
            </x-card>

            @include('club.events._resources_card', ['canParticipants' => $canManage])

            <x-card :title="__('club.matches.card.result')" icon="scoreboard">
                @if ($match->hasResult())
                    <p class="text-2xl font-semibold tabular-nums">{{ $match->result_summary ?? __('club.matches.label.result_recorded') }}</p>
                    @if ($match->result_note)<p class="mt-1 whitespace-pre-line text-sm">{{ $match->result_note }}</p>@endif
                    <p class="mt-2 text-xs text-muted">{{ __('club.matches.label.result_by', ['when' => $match->result_recorded_at?->orgTz()->format('d.m.Y H:i'), 'name' => $match->resultRecordedBy?->name ?? '–']) }}</p>
                @else
                    <p class="text-sm text-muted">{{ __('club.matches.empty.result') }}</p>
                @endif
            </x-card>

            <x-card :title="__('club.matches.card.roles')" icon="sports" :count="$roles->count()">
                <ul class="space-y-1 text-sm">
                    @forelse ($roles as $role)
                        <li class="flex items-center gap-2">
                            <x-icon :name="$role->role->icon()" class="text-muted" />
                            <span class="font-medium">{{ $role->role->label() }}</span>
                            <span>{{ $role->holderName() }}</span>
                            @if ($role->note)<span class="text-xs text-muted">{{ $role->note }}</span>@endif
                            @if ($canManage && ! $isCancelled)
                                <x-action-form :action="route('club.matches.roles.destroy', [$event, $role])" method="DELETE" class="ml-auto">
                                    <x-icon-btn type="submit" icon="close" tone="ghost" size="xs" :label="__('club.action.delete')" />
                                </x-action-form>
                            @endif
                        </li>
                    @empty
                        <li class="text-muted">{{ __('club.matches.empty.roles') }}</li>
                    @endforelse
                </ul>
                <p class="mt-2 text-xs text-muted">{{ __('club.matches.hint.roles') }}</p>
            </x-card>
        </div>
    </div>
</x-page-shell>
@endsection
