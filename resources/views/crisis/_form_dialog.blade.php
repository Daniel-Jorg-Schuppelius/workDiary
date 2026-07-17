{{-- Dialog: Krise melden (Feature 070, MVP-212) --}}
<x-modal
    :title="__('Krise melden')"
    :eyebrow="__('Krisenmanagement')"
    icon="emergency_home"
    tone="error"
    :action="route('crisis.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Krisenakte eröffnen')"
>
    <x-form-group :legend="__('Krise')" icon="emergency_home" tone="error" cols="2">
        <x-input-field name="title" :label="__('Titel')" required maxlength="200" span="2" :value="old('title')" />
        <x-select-field name="category" :label="__('Kategorie')" required :hint="__('Bestimmt die Meldefristen (DSGVO/NIS2/KRITIS).')">
            @foreach (\App\Models\Crisis\CrisisCase::CATEGORIES as $category)
                <option value="{{ $category }}" @selected(old('category', 'it_outage') === $category)>{{ __("values.$category") }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="severity" :label="__('Schweregrad')" required>
            @foreach (\App\Models\Crisis\CrisisCase::SEVERITIES as $severity)
                <option value="{{ $severity }}" @selected(old('severity', 'major') === $severity)>{{ __("values.$severity") }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="trigger_source" :label="__('Auslöser (Ticket/Vorfall/extern)')" maxlength="200" span="2" :value="old('trigger_source')" />
        <x-textarea-field name="description" :label="__('Lage/Beschreibung')" rows="3" span="2">{{ old('description') }}</x-textarea-field>
        <x-textarea-field name="affected_summary" :label="__('Betroffen (Standorte/Services/Kunden/Assets)')" rows="2" span="2">{{ old('affected_summary') }}</x-textarea-field>
    </x-form-group>

    <x-validation-errors />
</x-modal>
