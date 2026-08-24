{{--
  Created on   : Sat Jun 14 2026
  Author       : Daniel Jörg Schuppelius
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  Self-Service: eigene Verfügbarkeiten & Wunschdienste (Feature 007).
--}}

@extends('layouts.app')
@section('title', __('schedule.availability.title'))
@section('nav-title', __('schedule.availability.title'))

@php
    use App\Enums\Shift\AvailabilityKind;
    use App\Enums\Shift\ShiftPreference;
    $weekdays = [
        1 => __('Montag'), 2 => __('Dienstag'), 3 => __('Mittwoch'),
        4 => __('Donnerstag'), 5 => __('Freitag'), 6 => __('Samstag'), 0 => __('Sonntag'),
    ];
@endphp

@section('content')
    <x-index-page :subtitle="__('schedule.availability.subtitle')">

        @include('schedule._duty_tabs')

        <x-validation-errors />

        {{-- ── Verfügbarkeiten ─────────────────────────────────────────── --}}
        {{-- Bewusst Inline-Quick-Add statt _form_dialog (Modal-first-Ausnahme,
             Vollscan 2026-08 I9): Self-Service-Serienerfassung — mehrere
             Fenster/Wünsche direkt nacheinander, ein Modal pro Zeile würde
             den Fluss brechen. --}}
        <x-form-group :legend="__('schedule.availability.windows_legend')" icon="event_available" tone="primary">
            <form method="POST" action="{{ route('schedule.availability.windows.store') }}"
                  class="grid grid-cols-1 gap-2 md:grid-cols-6 md:items-end">
                @csrf
                <div class="fieldset">
                    <label class="fieldset-label" for="aw-weekday">{{ __('Wochentag') }}</label>
                    <select id="aw-weekday" name="weekday" class="select select-bordered select-sm w-full">
                        <option value="">{{ __('—') }}</option>
                        @foreach ($weekdays as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="fieldset">
                    <label class="fieldset-label" for="aw-date">{{ __('oder Datum') }}</label>
                    <input id="aw-date" type="date" name="specific_date" class="input input-bordered input-sm w-full">
                </div>
                <div class="fieldset">
                    <label class="fieldset-label" for="aw-kind">{{ __('Art') }}</label>
                    <select id="aw-kind" name="kind" class="select select-bordered select-sm w-full">
                        @foreach (AvailabilityKind::cases() as $kind)
                            <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="fieldset">
                    <label class="fieldset-label" for="aw-priority">{{ __('schedule.wish.priority_label') }}</label>
                    <select id="aw-priority" name="priority" class="select select-bordered select-sm w-full">
                        <option value="">{{ __('schedule.wish.priority_none') }}</option>
                        @foreach ([1, 2, 3] as $prio)
                            <option value="{{ $prio }}">{{ __('schedule.wish.priority_' . $prio) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="fieldset">
                    <label class="fieldset-label" for="aw-start">{{ __('Von') }}</label>
                    <input id="aw-start" type="time" name="start_time" class="input input-bordered input-sm w-full">
                </div>
                <div class="fieldset">
                    <label class="fieldset-label" for="aw-end">{{ __('Bis') }}</label>
                    <input id="aw-end" type="time" name="end_time" class="input input-bordered input-sm w-full">
                </div>
                <div class="fieldset">
                    <x-button type="submit" tone="primary" class="w-full">{{ __('Hinzufügen') }}</x-button>
                </div>
            </form>

            <x-table :zebra="true" class="mt-3">
                <x-slot:head>
                    <tr>
                        <th>{{ __('Wann') }}</th>
                        <th>{{ __('Art') }}</th>
                        <th>{{ __('Zeit') }}</th>
                        <th>{{ __('Notiz') }}</th>
                        <th class="text-right">{{ __('Aktion') }}</th>
                    </tr>
                </x-slot:head>
                @forelse ($windows as $window)
                    <tr class="hover">
                        <td>
                            @if ($window->specific_date)
                                {{ $window->specific_date->format('d.m.Y') }}
                            @else
                                {{ $weekdays[$window->weekday] ?? '—' }}
                                @if ($window->valid_from || $window->valid_until)
                                    <span class="text-xs opacity-60">
                                        ({{ $window->valid_from?->format('d.m.Y') ?? '…' }}–{{ $window->valid_until?->format('d.m.Y') ?? '…' }})
                                    </span>
                                @endif
                            @endif
                        </td>
                        <td>
                            <x-status-badge :tone="$window->kind->tone()" size="sm">{{ $window->kind->label() }}</x-status-badge>
                            @if ($window->priority)
                                <span class="text-xs opacity-60">{{ __('schedule.wish.priority_short') }} {{ $window->priority }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap">
                            @if ($window->start_time || $window->end_time)
                                {{ \Illuminate\Support\Str::of($window->start_time)->substr(0, 5) }}–{{ \Illuminate\Support\Str::of($window->end_time)->substr(0, 5) }}
                            @else {{ __('ganztägig') }} @endif
                        </td>
                        <td class="text-xs">{{ $window->note }}</td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('schedule.availability.windows.destroy', $window) }}" class="inline">
                                @csrf @method('DELETE')
                                <x-button type="submit" tone="ghost" size="xs" class="text-error">{{ __('Löschen') }}</x-button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="5" :title="__('schedule.availability.no_windows')" />
                @endforelse
            </x-table>
        </x-form-group>

        {{-- ── Wunschdienste ───────────────────────────────────────────── --}}
        {{-- Inline-Quick-Add: gleiche Ausnahme wie oben (Serienerfassung). --}}
        <x-form-group :legend="__('schedule.availability.desired_legend')" icon="favorite" tone="success">
            <form method="POST" action="{{ route('schedule.availability.desired.store') }}"
                  class="grid grid-cols-1 gap-2 md:grid-cols-5 md:items-end">
                @csrf
                <div class="fieldset">
                    <label class="fieldset-label" for="ds-date">{{ __('Datum') }}</label>
                    <input id="ds-date" type="date" name="date" class="input input-bordered input-sm w-full" required>
                </div>
                <div class="fieldset">
                    <label class="fieldset-label" for="ds-type">{{ __('Schichttyp') }}</label>
                    <select id="ds-type" name="shift_type_id" class="select select-bordered select-sm w-full">
                        <option value="">{{ __('beliebig') }}</option>
                        @foreach ($shiftTypes as $type)
                            <option value="{{ $type->sqid }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="fieldset">
                    <label class="fieldset-label" for="ds-pref">{{ __('Wunsch') }}</label>
                    <select id="ds-pref" name="preference" class="select select-bordered select-sm w-full">
                        @foreach (ShiftPreference::cases() as $pref)
                            <option value="{{ $pref->value }}">{{ $pref->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="fieldset">
                    <label class="fieldset-label" for="ds-priority">{{ __('schedule.wish.priority_label') }}</label>
                    <select id="ds-priority" name="priority" class="select select-bordered select-sm w-full">
                        <option value="">{{ __('schedule.wish.priority_none') }}</option>
                        @foreach ([1, 2, 3] as $prio)
                            <option value="{{ $prio }}">{{ __('schedule.wish.priority_' . $prio) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="fieldset">
                    <label class="fieldset-label" for="ds-note">{{ __('Notiz') }}</label>
                    <input id="ds-note" type="text" name="note" class="input input-bordered input-sm w-full" maxlength="255">
                </div>
                <div class="fieldset">
                    <x-button type="submit" tone="success" class="w-full">{{ __('Hinzufügen') }}</x-button>
                </div>
            </form>

            <x-table :zebra="true" class="mt-3">
                <x-slot:head>
                    <tr>
                        <th>{{ __('Datum') }}</th>
                        <th>{{ __('Schichttyp') }}</th>
                        <th>{{ __('Wunsch') }}</th>
                        <th>{{ __('Notiz') }}</th>
                        <th class="text-right">{{ __('Aktion') }}</th>
                    </tr>
                </x-slot:head>
                @forelse ($desired as $wish)
                    <tr class="hover">
                        <td>{{ $wish->date->format('d.m.Y') }}</td>
                        <td>{{ $wish->shiftType?->name ?? __('beliebig') }}</td>
                        <td>
                            <x-status-badge :tone="$wish->preference->tone()" size="sm">{{ $wish->preference->label() }}</x-status-badge>
                            @if ($wish->priority)
                                <span class="text-xs opacity-60">{{ __('schedule.wish.priority_short') }} {{ $wish->priority }}</span>
                            @endif
                        </td>
                        <td class="text-xs">{{ $wish->note }}</td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('schedule.availability.desired.destroy', $wish) }}" class="inline">
                                @csrf @method('DELETE')
                                <x-button type="submit" tone="ghost" size="xs" class="text-error">{{ __('Löschen') }}</x-button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="5" :title="__('schedule.availability.no_desired')" />
                @endforelse
            </x-table>
        </x-form-group>

    </x-index-page>
@endsection
