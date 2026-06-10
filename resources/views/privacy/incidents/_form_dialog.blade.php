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

    <x-form-group :legend="__('Vorfall')" icon="gpp_maybe" tone="primary" cols="2">
        <x-input-field name="type" :label="__('Art des Vorfalls')">
            <select id="type" name="type" class="select select-bordered w-full">
                @foreach ($types as $t)<option value="{{ $t->value }}" @selected(old('type') === $t->value)>{{ $t->label() }}</option>@endforeach
            </select>
        </x-input-field>
        <x-input-field name="occurred_at" type="datetime-local" :label="__('Zeitpunkt des Ereignisses')" :value="old('occurred_at')" />
        <x-input-field name="summary" :label="__('Sachverhalt')" required span="2">
            <textarea id="summary" name="summary" rows="4" class="textarea textarea-bordered w-full" required>{{ old('summary') }}</textarea>
            <p class="text-xs text-base-content/60 mt-1">{{ __('Wird verschlüsselt gespeichert.') }}</p>
        </x-input-field>
        <x-input-field name="affected" :label="__('Betroffene Daten / Personen / Systeme')" span="2">
            <textarea id="affected" name="affected" rows="3" class="textarea textarea-bordered w-full">{{ old('affected') }}</textarea>
        </x-input-field>
    </x-form-group>
</x-modal>
