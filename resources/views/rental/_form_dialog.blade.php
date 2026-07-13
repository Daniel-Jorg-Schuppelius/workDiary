{{-- Dialog: neue Verleihakte (Feature 073, MVP-261) --}}
<x-modal
    :title="__('Neue Verleihakte')"
    :eyebrow="__('Verleih')"
    icon="forklift"
    tone="primary"
    :action="route('rental.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Akte anlegen')"
>
    <x-form-group :legend="__('Kunde & Zeitraum')" icon="forklift" tone="primary" cols="2">
        <x-select-field name="customer_id" :label="__('Kunde')" required>
            <option value="">{{ __('-- Kunde wählen --') }}</option>
            @foreach ($customers as $c)
                <option value="{{ $c->sqid }}" @selected((string) old('customer_id') === $c->sqid)>{{ $c->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="contact_name" :label="__('Ansprechpartner (optional)')" :value="old('contact_name')" />
        <x-date-range class="md:col-span-2" layout="split" form-control required
                      from-name="starts_at" to-name="ends_at" type="datetime-local"
                      :from-label="__('Beginn')" :to-label="__('Ende')"
                      :from="old('starts_at')" :to="old('ends_at')" />
        <x-select-field name="project_id" :label="__('Projekt (optional)')">
            <option value="">{{ __('ohne Projektbezug') }}</option>
            @foreach ($projects as $p)
                <option value="{{ $p->sqid }}" @selected((string) old('project_id') === $p->sqid)>{{ $p->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="responsible_user_id" :label="__('Verantwortlich')">
            <option value="">{{ __('-- später zuweisen --') }}</option>
            @foreach ($users as $u)
                <option value="{{ $u->sqid }}" @selected((string) old('responsible_user_id') === $u->sqid)>{{ $u->name }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>

    <x-form-group :legend="__('Leihobjekte')" icon="inventory_2" tone="primary" cols="1">
        <x-select-field name="asset_ids[]" :label="__('Geräte/Maschinen (Mehrfachauswahl)')" multiple size="6">
            @foreach ($assets as $a)
                <option value="{{ $a->sqid }}" @selected(collect(old('asset_ids', []))->contains($a->sqid))>
                    {{ $a->name }}@if ($a->rentalProfile?->group_code) ({{ $a->rentalProfile->group_code }})@endif
                </option>
            @endforeach
        </x-select-field>
    </x-form-group>

    <x-form-group :legend="__('Konditionen & Orte')" icon="request_quote" tone="primary" cols="2">
        <x-select-field name="rental_rate_card_id" :label="__('Preisliste (Version wird eingefroren)')">
            <option value="">{{ __('ohne Preisliste (manuelle Positionen)') }}</option>
            @foreach ($rateCards as $card)
                <option value="{{ $card->sqid }}" @selected((string) old('rental_rate_card_id') === $card->sqid)>{{ $card->name }} (v{{ $card->version }})</option>
            @endforeach
        </x-select-field>
        <x-input-field name="deposit_amount" type="number" step="0.01" min="0" :label="__('Kaution (optional, eigener Vorgang)')" :value="old('deposit_amount')" />
        <x-input-field name="handover_location" :label="__('Übergabeort')" :value="old('handover_location')" />
        <x-input-field name="return_location" :label="__('Rückgabeort')" :value="old('return_location')" />
        <x-input-field name="insurance_note" :label="__('Versicherungshinweis')" :value="old('insurance_note')" span="2" />
        <x-textarea-field name="notes" :label="__('Notizen')" rows="2" span="2">{{ old('notes') }}</x-textarea-field>
    </x-form-group>

    @if ($errors->any())
        <div class="alert alert-error text-sm">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
</x-modal>
