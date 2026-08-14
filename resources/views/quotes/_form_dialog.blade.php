{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: neues Angebot (Feature 066, MVP-170) --}}
<x-modal
    :title="__('Neues Angebot')"
    :eyebrow="__('Angebot')"
    icon="request_quote"
    tone="primary"
    :action="route('quotes.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Entwurf anlegen')"
>
    <x-form-group :legend="__('Angebot')" icon="request_quote" tone="primary" cols="2">
        <x-select-field name="customer_id" :label="__('Kunde')" required span="2">
            <option value="">{{ __('-- bitte wählen --') }}</option>
            @foreach ($customers as $c)
                <option value="{{ $c->sqid }}" @selected((string) old('customer_id') === $c->sqid)>{{ $c->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="project_id" :label="__('Projekt (optional)')" span="2" data-depends-on="customer_id">
            <option value="">{{ __('ohne Projektbezug') }}</option>
            @foreach ($projects as $p)
                <option value="{{ $p->sqid }}" data-parent="{{ \App\Support\Sqid::encode(\App\Models\Customer::class, $p->customer_id) }}" @selected((string) old('project_id') === $p->sqid)>{{ $p->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="valid_until" type="date" :label="__('Bindefrist (gültig bis)')" :value="old('valid_until')" />
        <x-textarea-field name="terms" :label="__('Bedingungen / Leistungsumfang (optional)')" rows="3" span="2">{{ old('terms') }}</x-textarea-field>
    </x-form-group>

    <x-validation-errors />
</x-modal>
