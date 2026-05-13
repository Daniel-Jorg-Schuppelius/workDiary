{{-- Erwartet: $project, $entry (null = neu), $tasks, $isDialog --}}
@php
    $isDialog  = $isDialog ?? false;
    $action    = $entry
        ? route('projects.time-entries.update', [$project, $entry])
        : route('projects.time-entries.store', $project);
    $dialogUrl = ($entry
        ? route('projects.time-entries.edit', [$project, $entry])
        : route('projects.time-entries.create', $project)) . '?dialog=1';

    // HH:MM-Wert aus minutes berechnen
    $currentMinutes = old('minutes', $entry?->minutes ?? 60);
    $hh = str_pad((string) intdiv((int) $currentMinutes, 60), 2, '0', STR_PAD_LEFT);
    $mm = str_pad((string) ((int) $currentMinutes % 60), 2, '0', STR_PAD_LEFT);
@endphp

<x-dialog
    :title="$entry ? __('Zeiteintrag bearbeiten') : __('Zeiteintrag erfassen')"
    :eyebrow="__('Zeiterfassung')"
    icon="⏱"
    tone="primary">
    <form method="POST" action="{{ $action }}" class="space-y-4" id="time-entry-form" data-entry-form>
        @csrf
        @if ($entry) @method('PUT') @endif
        @if ($isDialog)
            <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
        @endif

        {{-- Minuten als verstecktes Feld; wird per JS aus HH:MM befüllt --}}
        <input type="hidden" name="minutes" id="time_minutes_hidden" value="{{ $currentMinutes }}">

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Datum') }}</label>
                <input name="date" type="date"
                       class="input input-bordered w-full"
                       value="{{ old('date', $entry?->date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                       required>
                @error('date')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>

            <div class="fieldset">
                <label class="fieldset-label">{{ __('Dauer (HH:MM)') }}</label>
                <input type="text" id="time_hhmm_input"
                       class="input input-bordered w-full"
                       pattern="^\d{1,2}:[0-5]\d$"
                       placeholder="1:30"
                       value="{{ $hh }}:{{ $mm }}"
                       required>
                @error('minutes')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>
        </div>

        @if ($tasks->isNotEmpty())
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Aufgabe (optional)') }}</label>
                <select name="task_id" class="select select-bordered w-full">
                    <option value="">{{ __('Keine Aufgabe') }}</option>
                    @foreach ($tasks as $t)
                        <option value="{{ $t->id }}" @selected(old('task_id', $entry?->task_id) == $t->id)>{{ $t->title }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="fieldset">
            <label class="fieldset-label">{{ __('Beschreibung') }}</label>
            <input name="description" type="text" maxlength="500"
                   class="input input-bordered w-full"
                   value="{{ old('description', $entry?->description) }}">
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-2">
            <button type="submit" class="btn btn-sm btn-primary">
                {{ $entry ? __('Speichern') : __('Erfassen') }}
            </button>
            @if ($isDialog)
                <button type="button" class="btn btn-sm btn-ghost" data-entry-modal-close>{{ __('Abbrechen') }}</button>
            @else
                <a href="{{ route('projects.show', $project) }}#time" class="btn btn-sm btn-ghost">{{ __('Abbrechen') }}</a>
            @endif
        </div>
    </form>
</x-dialog>

<script>
(function () {
    const hhmm  = document.getElementById('time_hhmm_input');
    const hidden = document.getElementById('time_minutes_hidden');
    if (!hhmm || !hidden) return;

    function toMinutes(val) {
        const parts = val.split(':');
        if (parts.length !== 2) return null;
        const h = parseInt(parts[0], 10);
        const m = parseInt(parts[1], 10);
        if (isNaN(h) || isNaN(m) || m < 0 || m > 59) return null;
        return h * 60 + m;
    }

    hhmm.addEventListener('input', function () {
        const min = toMinutes(this.value);
        hidden.value = min !== null ? String(min) : '';
    });

    document.getElementById('time-entry-form').addEventListener('submit', function (e) {
        const min = toMinutes(hhmm.value);
        if (min === null || min < 1 || min > 1440) {
            e.preventDefault();
            hhmm.setCustomValidity('{{ __("Bitte gültige Dauer eingeben (z. B. 1:30).") }}');
            hhmm.reportValidity();
        } else {
            hhmm.setCustomValidity('');
            hidden.value = String(min);
        }
    });
})();
</script>
