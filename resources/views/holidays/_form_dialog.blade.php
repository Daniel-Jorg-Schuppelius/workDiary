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

<x-dialog
    :title="$isEdit ? __('Feiertag bearbeiten') : __('Feiertag anlegen')"
    :eyebrow="__('Kalender')"
    icon="🎉"
    tone="warning">
    <form method="POST" action="{{ $action }}" class="space-y-4" data-entry-form data-recurrence-form>
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif
        @if ($isDialog)
            <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
        @endif

        {{-- Wiederholungs-Typ --}}
        <div>
            <label class="label" for="recurrence-mode"><span class="label-text">{{ __('Typ') }}</span></label>
            <select id="recurrence-mode" name="recurrence_mode"
                    class="select select-bordered w-full"
                    data-recurrence-select>
                <option value="once"     @selected(old('recurrence_mode', $recurrenceMode) === 'once')>{{ __('Einmalig') }}</option>
                <option value="yearly"   @selected(old('recurrence_mode', $recurrenceMode) === 'yearly')>{{ __('Jährlich – festes Datum') }}</option>
                <option value="relative" @selected(old('recurrence_mode', $recurrenceMode) === 'relative')>{{ __('Relativer Wochentag (z. B. letzter Freitag)') }}</option>
            </select>
        </div>

        {{-- Datum (für einmalig / jährlich-fest) --}}
        <div data-recurrence-show="once yearly">
            <label class="label" for="holiday-date">
                <span class="label-text">{{ __('Datum') }}</span>
                <span class="label-text-alt text-base-content/50" data-recurrence-show="yearly">{{ __('Nur Tag & Monat werden verwendet') }}</span>
            </label>
            <input id="holiday-date" type="date" name="date"
                   value="{{ old('date', optional($holiday?->date)->format('Y-m-d')) }}"
                   class="input input-bordered w-full {{ $errors->has('date') ? 'input-error' : '' }}" >
            @if ($errors->has('date'))
                <p class="mt-1 text-sm text-error">{{ $errors->first('date') }}</p>
            @endif
        </div>

        {{-- Relative Felder (Woche / Wochentag / Monat) --}}
        <div data-recurrence-show="relative" class="grid grid-cols-3 gap-3">
            <div>
                <label class="label" for="rec-week"><span class="label-text">{{ __('Woche') }}</span></label>
                <select id="rec-week" name="recurrence_week" class="select select-bordered w-full">
                    @foreach ($weekNumbers as $val => $label)
                        <option value="{{ $val }}" @selected((int) old('recurrence_week', $holiday?->recurrence_week ?? 1) === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label" for="rec-weekday"><span class="label-text">{{ __('Wochentag') }}</span></label>
                <select id="rec-weekday" name="recurrence_weekday" class="select select-bordered w-full">
                    @foreach ($weekdays as $val => $label)
                        <option value="{{ $val }}" @selected((int) old('recurrence_weekday', $holiday?->recurrence_weekday ?? 1) === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label" for="rec-month"><span class="label-text">{{ __('Monat') }}</span></label>
                <select id="rec-month" name="recurrence_month" class="select select-bordered w-full">
                    <option value="">{{ __('Jeden Monat') }}</option>
                    @foreach ($monthNames as $val => $label)
                        <option value="{{ $val }}" @selected((int) old('recurrence_month', $holiday?->recurrence_month ?? 0) === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Name --}}
        <div>
            <label class="label" for="holiday-name"><span class="label-text">{{ __('Name') }}</span></label>
            <input id="holiday-name" type="text" name="name" value="{{ old('name', $holiday?->name) }}" maxlength="120" class="input input-bordered w-full {{ $errors->has('name') ? 'input-error' : '' }}" required>
            @if ($errors->has('name'))
                <p class="mt-1 text-sm text-error">{{ $errors->first('name') }}</p>
            @endif
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="btn btn-sm btn-ghost" data-entry-modal-close>{{ __('Abbrechen') }}</button>
            <button type="submit" class="btn btn-sm btn-primary">{{ $isEdit ? __('Speichern') : __('Anlegen') }}</button>
        </div>
    </form>
</x-dialog>
