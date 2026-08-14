{{--
  Created on   : Mon May 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@php
    $isEdit = $isEdit ?? false;
    $isDialog = $isDialog ?? true;
    $action = $isEdit ? route('holidays.update', $holiday) : route('holidays.store');
    $dialogUrl = ($isEdit ? route('holidays.edit', $holiday) : route('holidays.create')) . '?dialog=1';

    // Aktuellen Modus aus dem Modell ableiten
    $recurrenceMode = match(true) {
        ($holiday?->recurrence_type ?? 'fixed') === 'relative' => 'relative',
        ($holiday?->is_recurring ?? false) => 'yearly',
        default => 'once',
    };

    $weekdays = [
        0 => __('Sonntag'), 1 => __('Montag'), 2 => __('Dienstag'),
        3 => __('Mittwoch'), 4 => __('Donnerstag'), 5 => __('Freitag'), 6 => __('Samstag'),
    ];
    $weekNumbers = [
        1 => __('1.'), 2 => __('2.'), 3 => __('3.'), 4 => __('4.'), -1 => __('Letzter/Letzte'),
    ];
    $monthNames = [
        1 => __('Januar'), 2 => __('Februar'), 3 => __('März'), 4 => __('April'),
        5 => __('Mai'), 6 => __('Juni'), 7 => __('Juli'), 8 => __('August'),
        9 => __('September'), 10 => __('Oktober'), 11 => __('November'), 12 => __('Dezember'),
    ];
@endphp

<x-modal
    :title="$isEdit ? __('Feiertag bearbeiten') : __('Feiertag anlegen')"
    :eyebrow="__('Kalender')"
    icon="celebration"
    tone="warning"
    :action="$action"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '', 'data-recurrence-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
    @endif

    <x-form-group :legend="__('Wiederholung')" icon="sync" tone="warning">
            <div class="fieldset">
                <label class="fieldset-label" for="recurrence-mode">{{ __('Typ') }}</label>
                <select id="recurrence-mode" name="recurrence_mode"
                        class="select select-bordered w-full"
                        data-recurrence-select>
                    <option value="once"     @selected(old('recurrence_mode', $recurrenceMode) === 'once')>{{ __('Einmalig') }}</option>
                    <option value="yearly"   @selected(old('recurrence_mode', $recurrenceMode) === 'yearly')>{{ __('Jährlich – festes Datum') }}</option>
                    <option value="relative" @selected(old('recurrence_mode', $recurrenceMode) === 'relative')>{{ __('Relativer Wochentag (z. B. letzter Freitag)') }}</option>
                </select>
            </div>

            <div class="fieldset" data-recurrence-show="once yearly">
                <label class="fieldset-label" for="holiday-date">
                    {{ __('Datum') }}
                    <span class="ml-auto text-xs text-base-content/50" data-recurrence-show="yearly">{{ __('Nur Tag & Monat werden verwendet') }}</span>
                </label>
                <input id="holiday-date" type="date" name="date"
                       value="{{ old('date', optional($holiday?->date)->format('Y-m-d')) }}"
                       class="input input-bordered w-full {{ $errors->has('date') ? 'input-error' : '' }}" >
                @if ($errors->has('date'))
                    <p class="text-error text-sm">{{ $errors->first('date') }}</p>
                @endif
            </div>

            <div data-recurrence-show="relative" class="grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="fieldset">
                    <label class="fieldset-label" for="rec-week">{{ __('Woche') }}</label>
                    <select id="rec-week" name="recurrence_week" class="select select-bordered w-full">
                        @foreach ($weekNumbers as $val => $label)
                            <option value="{{ $val }}" @selected((int) old('recurrence_week', $holiday?->recurrence_week ?? 1) === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="fieldset">
                    <label class="fieldset-label" for="rec-weekday">{{ __('Wochentag') }}</label>
                    <select id="rec-weekday" name="recurrence_weekday" class="select select-bordered w-full">
                        @foreach ($weekdays as $val => $label)
                            <option value="{{ $val }}" @selected((int) old('recurrence_weekday', $holiday?->recurrence_weekday ?? 1) === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="fieldset">
                    <label class="fieldset-label" for="rec-month">{{ __('Monat') }}</label>
                    <select id="rec-month" name="recurrence_month" class="select select-bordered w-full">
                        <option value="">{{ __('Jeden Monat') }}</option>
                        @foreach ($monthNames as $val => $label)
                            <option value="{{ $val }}" @selected((int) old('recurrence_month', $holiday?->recurrence_month ?? 0) === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </x-form-group>

        <x-form-group :legend="__('Bezeichnung')" icon="label" tone="primary">
            <div class="fieldset">
                <label class="fieldset-label" for="holiday-name">{{ __('Name') }}</label>
                <input id="holiday-name" type="text" name="name" value="{{ old('name', $holiday?->name) }}" maxlength="120" class="input input-bordered w-full {{ $errors->has('name') ? 'input-error' : '' }}" required>
                @if ($errors->has('name'))
                    <p class="text-error text-sm">{{ $errors->first('name') }}</p>
                @endif
            </div>
        </x-form-group>
</x-modal>
