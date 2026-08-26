{{--
  Created on   : Wed Aug 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Rollplan-Pflege (MVP-522): Rhythmen (Wochenraster × Schichttyp),
  Zuweisungen und manuelle Fortschreibung.
--}}

@extends('layouts.app')
@section('title', __('Rollpläne'))
@section('nav-title', __('Rollpläne'))

@section('content')
@php
    $weekdays = [1 => __('Mo'), 2 => __('Di'), 3 => __('Mi'), 4 => __('Do'), 5 => __('Fr'), 6 => __('Sa'), 7 => __('So')];
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Rollierende Dienstrhythmen — schreiben sich automatisch als Vorplanung fort.')">
            <x-slot:actions>
                <form method="POST" action="{{ route('admin.shift-rotations.roll') }}" class="inline-flex items-center gap-1">
                    @csrf
                    <input type="number" name="weeks" min="1" max="26" value="4"
                           class="input input-sm input-bordered w-16" aria-label="{{ __('Wochen') }}">
                    <button type="submit" class="btn btn-sm btn-outline">{{ __('Jetzt fortschreiben') }}</button>
                </form>
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('admin.shift-rotations.create')"
                            show-label>{{ __('Rollplan anlegen') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($rotations->isEmpty())
        <x-empty-state framed
            icon="event_repeat"
            :title="__('Keine Rollpläne')"
            :message="__('Legen Sie einen Rhythmus an, um die Dienst-Vorplanung zu automatisieren.')" />
    @else
        @foreach ($rotations as $rotation)
            @php
                $slotMap = [];
                foreach ($rotation->entries as $entry) {
                    $slotMap[$entry->week_index . '|' . $entry->iso_weekday] = (int) $entry->shift_type_id;
                }
            @endphp
            <x-card>
                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <h3 class="font-semibold text-lg">{{ $rotation->name }}</h3>
                    <x-status-badge :tone="$rotation->is_active ? 'success' : 'ghost'" size="sm">
                        {{ $rotation->is_active ? __('aktiv') : __('inaktiv') }}
                    </x-status-badge>
                    <span class="text-sm text-muted">{{ __(':weeks Wochen-Rhythmus', ['weeks' => $rotation->weeks_count]) }}</span>
                    <form method="POST" action="{{ route('admin.shift-rotations.toggle', $rotation) }}" class="ml-auto">
                        @csrf
                        <button type="submit" class="btn btn-xs btn-ghost">
                            {{ $rotation->is_active ? __('Deaktivieren') : __('Aktivieren') }}
                        </button>
                    </form>
                </div>

                {{-- Wochenraster --}}
                <form method="POST" action="{{ route('admin.shift-rotations.entries', $rotation) }}">
                    @csrf
                    @method('PUT')
                    <div class="overflow-x-auto">
                        <table class="table table-xs">
                            <thead>
                                <tr>
                                    <th>{{ __('Woche') }}</th>
                                    @foreach ($weekdays as $label)
                                        <th>{{ $label }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @for ($week = 0; $week < $rotation->weeks_count; $week++)
                                    <tr>
                                        <td class="font-medium">{{ $week + 1 }}</td>
                                        @foreach ($weekdays as $dow => $label)
                                            <td>
                                                <select name="entries[{{ $week }}][{{ $dow }}]"
                                                        class="select select-xs select-bordered w-28">
                                                    <option value="">{{ __('— frei —') }}</option>
                                                    @foreach ($shiftTypes as $type)
                                                        <option value="{{ $type->sqid }}"
                                                                @selected(($slotMap[$week . '|' . $dow] ?? null) === (int) $type->id)>
                                                            {{ $type->abbreviation ?: $type->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endfor
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2 flex justify-end">
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('Wochenraster speichern') }}</button>
                    </div>
                </form>

                {{-- Zuweisungen --}}
                <div class="divider my-2"></div>
                <h4 class="font-medium mb-2">{{ __('Zuweisungen') }}</h4>
                @if ($rotation->assignments->isNotEmpty())
                    <x-table bare>
                        <x-slot:head>
                            <tr>
                                <x-table.th>{{ __('Mitarbeiter:in') }}</x-table.th>
                                <x-table.th>{{ __('Anker-Woche (Montag)') }}</x-table.th>
                                <x-table.th>{{ __('Gültig') }}</x-table.th>
                                <x-table.th></x-table.th>
                            </tr>
                        </x-slot:head>
                        @foreach ($rotation->assignments as $assignment)
                            <tr>
                                <td>{{ $assignment->user?->name }}</td>
                                <td class="tabular-nums">{{ $assignment->anchor_date->fdate() }}</td>
                                <td class="text-sm text-muted">
                                    {{ $assignment->valid_from?->fdate() ?? '—' }} – {{ $assignment->valid_until?->fdate() ?? '—' }}
                                </td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('admin.shift-rotations.assignments.destroy', $assignment) }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-icon-btn icon="delete" size="sm" tone="ghost" type="submit"
                                                    :aria-label="__('Entfernen')" />
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </x-table>
                @endif
                <form method="POST" action="{{ route('admin.shift-rotations.assignments.store', $rotation) }}"
                      class="mt-2 flex flex-wrap items-end gap-2">
                    @csrf
                    <div class="fieldset">
                        <label for="rot-{{ $rotation->sqid }}-user_id" class="fieldset-label">{{ __('Mitarbeiter:in') }}</label>
                        <select id="rot-{{ $rotation->sqid }}-user_id" name="user_id" class="select select-sm select-bordered w-52" required>
                            @foreach ($members as $member)
                                <option value="{{ $member->sqid }}">{{ $member->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fieldset">
                        <label for="rot-{{ $rotation->sqid }}-anchor_date" class="fieldset-label">{{ __('Anker-Woche (Montag)') }}</label>
                        <input id="rot-{{ $rotation->sqid }}-anchor_date" type="date" name="anchor_date" class="input input-sm input-bordered" required>
                    </div>
                    <x-date-range layout="split" form-control grid-class="contents"
                                  from-name="valid_from" to-name="valid_until" type="date"
                                  :from-label="__('Gültig ab')" :to-label="__('Gültig bis')" />
                    <button type="submit" class="btn btn-sm btn-outline">{{ __('Zuweisen') }}</button>
                </form>
            </x-card>
        @endforeach
    @endif
</x-page-shell>
@endsection
