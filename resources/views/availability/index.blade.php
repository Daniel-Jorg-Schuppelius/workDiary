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
                                {{ $window->specific_date->fdate() }}
                            @else
                                {{ $weekdays[$window->weekday] ?? '—' }}
                                @if ($window->valid_from || $window->valid_until)
                                    <span class="text-xs opacity-60">
                                        ({{ $window->valid_from?->fdate() ?? '…' }}–{{ $window->valid_until?->fdate() ?? '…' }})
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
                            <x-button tone="ghost" size="xs" icon="edit" data-open-dialog="aw-edit-{{ $loop->index }}">{{ __('Bearbeiten') }}</x-button>
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

            {{-- Bearbeiten je Fenster (lk-03): derselbe Request wie beim Anlegen. --}}
            @foreach ($windows as $window)
                <x-modal id="aw-edit-{{ $loop->index }}" :embedded="false" icon="event_available"
                         :title="__('schedule.availability.edit_window')"
                         :action="route('schedule.availability.windows.update', $window)" method="PUT"
                         :submit-label="__('Speichern')">
                    <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                        <div class="fieldset">
                            <label class="fieldset-label" for="aw-{{ $loop->index }}-weekday">{{ __('Wochentag') }}</label>
                            <select id="aw-{{ $loop->index }}-weekday" name="weekday" class="select select-bordered select-sm w-full">
                                <option value="">{{ __('—') }}</option>
                                @foreach ($weekdays as $val => $label)
                                    <option value="{{ $val }}" @selected($window->weekday !== null && (int) $window->weekday === $val)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="fieldset">
                            <label class="fieldset-label" for="aw-{{ $loop->index }}-date">{{ __('oder Datum') }}</label>
                            <input id="aw-{{ $loop->index }}-date" type="date" name="specific_date" value="{{ $window->specific_date?->toDateString() }}" class="input input-bordered input-sm w-full">
                        </div>
                        <div class="fieldset">
                            <label class="fieldset-label" for="aw-{{ $loop->index }}-kind">{{ __('Art') }}</label>
                            <select id="aw-{{ $loop->index }}-kind" name="kind" class="select select-bordered select-sm w-full">
                                @foreach (AvailabilityKind::cases() as $kind)
                                    <option value="{{ $kind->value }}" @selected($window->kind === $kind)>{{ $kind->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="fieldset">
                            <label class="fieldset-label" for="aw-{{ $loop->index }}-priority">{{ __('schedule.wish.priority_label') }}</label>
                            <select id="aw-{{ $loop->index }}-priority" name="priority" class="select select-bordered select-sm w-full">
                                <option value="">{{ __('schedule.wish.priority_none') }}</option>
                                @foreach ([1, 2, 3] as $prio)
                                    <option value="{{ $prio }}" @selected((int) $window->priority === $prio)>{{ __('schedule.wish.priority_' . $prio) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="fieldset">
                            <label class="fieldset-label" for="aw-{{ $loop->index }}-start">{{ __('Von') }}</label>
                            <input id="aw-{{ $loop->index }}-start" type="time" name="start_time" value="{{ \Illuminate\Support\Str::of((string) $window->start_time)->substr(0, 5) }}" class="input input-bordered input-sm w-full">
                        </div>
                        <div class="fieldset">
                            <label class="fieldset-label" for="aw-{{ $loop->index }}-end">{{ __('Bis') }}</label>
                            <input id="aw-{{ $loop->index }}-end" type="time" name="end_time" value="{{ \Illuminate\Support\Str::of((string) $window->end_time)->substr(0, 5) }}" class="input input-bordered input-sm w-full">
                        </div>
                        <div class="fieldset">
                            <label class="fieldset-label" for="aw-{{ $loop->index }}-from">{{ __('Gültig ab') }}</label>
                            <input id="aw-{{ $loop->index }}-from" type="date" name="valid_from" value="{{ $window->valid_from?->toDateString() }}" class="input input-bordered input-sm w-full">
                        </div>
                        <div class="fieldset">
                            <label class="fieldset-label" for="aw-{{ $loop->index }}-until">{{ __('Gültig bis') }}</label>
                            <input id="aw-{{ $loop->index }}-until" type="date" name="valid_until" value="{{ $window->valid_until?->toDateString() }}" class="input input-bordered input-sm w-full">
                        </div>
                        <div class="fieldset md:col-span-2">
                            <label class="fieldset-label" for="aw-{{ $loop->index }}-note">{{ __('Notiz') }}</label>
                            <input id="aw-{{ $loop->index }}-note" type="text" name="note" value="{{ $window->note }}" maxlength="255" class="input input-bordered input-sm w-full">
                        </div>
                    </div>
                </x-modal>
            @endforeach
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
                        <td>{{ $wish->date->fdate() }}</td>
                        <td>{{ $wish->shiftType?->name ?? __('beliebig') }}</td>
                        <td>
                            <x-status-badge :tone="$wish->preference->tone()" size="sm">{{ $wish->preference->label() }}</x-status-badge>
                            @if ($wish->priority)
                                <span class="text-xs opacity-60">{{ __('schedule.wish.priority_short') }} {{ $wish->priority }}</span>
                            @endif
                        </td>
                        <td class="text-xs">{{ $wish->note }}</td>
                        <td class="text-right">
                            <x-button tone="ghost" size="xs" icon="edit" data-open-dialog="ds-edit-{{ $loop->index }}">{{ __('Bearbeiten') }}</x-button>
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

            @foreach ($desired as $wish)
                <x-modal id="ds-edit-{{ $loop->index }}" :embedded="false" icon="favorite"
                         :title="__('schedule.availability.edit_desired')"
                         :action="route('schedule.availability.desired.update', $wish)" method="PUT"
                         :submit-label="__('Speichern')">
                    <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                        <div class="fieldset">
                            <label class="fieldset-label" for="ds-{{ $loop->index }}-date">{{ __('Datum') }}</label>
                            <input id="ds-{{ $loop->index }}-date" type="date" name="date" value="{{ $wish->date->toDateString() }}" class="input input-bordered input-sm w-full" required>
                        </div>
                        <div class="fieldset">
                            <label class="fieldset-label" for="ds-{{ $loop->index }}-type">{{ __('Schichttyp') }}</label>
                            <select id="ds-{{ $loop->index }}-type" name="shift_type_id" class="select select-bordered select-sm w-full">
                                <option value="">{{ __('beliebig') }}</option>
                                @foreach ($shiftTypes as $type)
                                    <option value="{{ $type->sqid }}" @selected((int) $wish->shift_type_id === (int) $type->id)>{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="fieldset">
                            <label class="fieldset-label" for="ds-{{ $loop->index }}-pref">{{ __('Wunsch') }}</label>
                            <select id="ds-{{ $loop->index }}-pref" name="preference" class="select select-bordered select-sm w-full">
                                @foreach (ShiftPreference::cases() as $pref)
                                    <option value="{{ $pref->value }}" @selected($wish->preference === $pref)>{{ $pref->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="fieldset">
                            <label class="fieldset-label" for="ds-{{ $loop->index }}-priority">{{ __('schedule.wish.priority_label') }}</label>
                            <select id="ds-{{ $loop->index }}-priority" name="priority" class="select select-bordered select-sm w-full">
                                <option value="">{{ __('schedule.wish.priority_none') }}</option>
                                @foreach ([1, 2, 3] as $prio)
                                    <option value="{{ $prio }}" @selected((int) $wish->priority === $prio)>{{ __('schedule.wish.priority_' . $prio) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="fieldset md:col-span-2">
                            <label class="fieldset-label" for="ds-{{ $loop->index }}-note">{{ __('Notiz') }}</label>
                            <input id="ds-{{ $loop->index }}-note" type="text" name="note" value="{{ $wish->note }}" maxlength="255" class="input input-bordered input-sm w-full">
                        </div>
                    </div>
                </x-modal>
            @endforeach
        </x-form-group>

        <x-pagination :paginator="$desired" standing />

    </x-index-page>
@endsection
