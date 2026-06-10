{{-- Anlage-Dialog Datenschutzvorfall (in #entry-modal geladen). Variablen: $types --}}
<x-modal
    :title="__('Vorfall melden')"
    :eyebrow="__('Datenschutzvorfälle')"
    icon="gpp_maybe"
    tone="primary"
    :action="route('dataprotection.incidents.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Anlegen')">

    <div class="grid md:grid-cols-2 gap-3">
        <div>
            <label class="label" for="type">{{ __('Art des Vorfalls') }}</label>
            <select id="type" name="type" class="select select-bordered w-full">
                @foreach ($types as $t)<option value="{{ $t->value }}" @selected(old('type') === $t->value)>{{ $t->label() }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="label" for="occurred_at">{{ __('Zeitpunkt des Ereignisses') }}</label>
            <input id="occurred_at" name="occurred_at" type="datetime-local" class="input input-bordered w-full" value="{{ old('occurred_at') }}">
        </div>
    </div>
    <div>
        <label class="label" for="summary">{{ __('Sachverhalt') }}</label>
        <textarea id="summary" name="summary" rows="4" class="textarea textarea-bordered w-full" required>{{ old('summary') }}</textarea>
        <p class="text-xs text-base-content/60 mt-1">{{ __('Wird verschlüsselt gespeichert.') }}</p>
    </div>
    <div>
        <label class="label" for="affected">{{ __('Betroffene Daten / Personen / Systeme') }}</label>
        <textarea id="affected" name="affected" rows="3" class="textarea textarea-bordered w-full">{{ old('affected') }}</textarea>
    </div>
</x-modal>
