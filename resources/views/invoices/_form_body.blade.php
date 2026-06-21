{{-- Shared form fields for Invoice create --}}

<x-form-group :legend="__('Filter')" icon="receipt_long" tone="primary" cols="2">
    <x-select-field name="customer_id" :label="__('Kunde')" required span="2">
        <option value="">{{ __('-- bitte wählen --') }}</option>
        @foreach ($customers as $c)
            <option value="{{ $c->sqid }}" @selected((string) old('customer_id') === $c->sqid)>{{ $c->name }}</option>
        @endforeach
    </x-select-field>
    <x-select-field name="project_id" :label="__('Projekt (optional)')" span="2" data-depends-on="customer_id">
        <option value="">{{ __('alle Projekte des Kunden') }}</option>
        @foreach ($projects as $p)
            <option value="{{ $p->sqid }}" data-parent="{{ \App\Support\Sqid::encode(\App\Models\Customer::class, $p->customer_id) }}" @selected((string) old('project_id') === $p->sqid)>{{ $p->name }}</option>
        @endforeach
    </x-select-field>
    <x-select-field name="foreign_customer_id" :label="__('Fremdkunde / Endkunde (optional)')" span="2" data-depends-on="customer_id" :hint="__('Nur Zeiten dieses Endkunden abrechnen.')">
        <option value="">{{ __('alle Endkunden') }}</option>
        @foreach (($foreignCustomers ?? collect()) as $fc)
            <option value="{{ $fc->sqid }}" data-parent="{{ \App\Support\Sqid::encode(\App\Models\Customer::class, $fc->customer_id) }}" @selected((string) old('foreign_customer_id') === $fc->sqid)>{{ $fc->company ?: $fc->name }}</option>
        @endforeach
    </x-select-field>
    <x-select-field name="content" :label="__('Inhalt')" span="2" :hint="__('Material wird getrennt als eigene Rechnung mit Lieferdatum/-zeitraum erstellt.')">
        <option value="service" @selected(old('content', 'service') === 'service')>{{ __('Leistung (Zeit) — Leistungsdatum') }}</option>
        <option value="material" @selected(old('content') === 'material')>{{ __('Material — Lieferdatum') }}</option>
    </x-select-field>
    <x-input-field name="from" type="date" :label="__('Von')" :value="old('from', $defaultFrom ?? '')" />
    <x-input-field name="to" type="date" :label="__('Bis')" :value="old('to', $defaultTo ?? '')" />
</x-form-group>

@if ($errors->any())
    <div class="alert alert-error text-sm">
        <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif
