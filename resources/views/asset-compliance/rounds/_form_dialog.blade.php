{{--
  Created on   : Fri Sep 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Prüfmittelrunde anlegen (MVP-899); die Soll-Liste wird beim Speichern eingefroren. --}}
<x-modal :title="__('inspection_round.open')" :eyebrow="__('inspection_round.title')" icon="qr_code_scanner" tone="primary"
         :action="route('asset-compliance.rounds.store')" method="POST"
         :form-data="['data-entry-form' => '']" :submit-label="__('inspection_round.open')">
    <x-input-field name="name" :label="__('inspection_round.name')" :value="old('name')" required maxlength="180" />
    <x-input-field name="due_until" type="date" :label="__('inspection_round.due_until')" :value="old('due_until', now()->addDays(30)->toDateString())" required />
    <x-select-field name="location_text" :label="__('inspection_round.location')">
        <option value="">{{ __('inspection_round.any') }}</option>
        @foreach ($locations as $location)
            <option value="{{ $location }}" @selected(old('location_text') === $location)>{{ $location }}</option>
        @endforeach
    </x-select-field>
    <x-select-field name="category_code" :label="__('inspection_round.category')">
        <option value="">{{ __('inspection_round.any') }}</option>
        @foreach ($categories as $category)
            <option value="{{ $category }}" @selected(old('category_code') === $category)>{{ $category }}</option>
        @endforeach
    </x-select-field>
    <x-select-field name="asset_compliance_profile_id" :label="__('inspection_round.profile')">
        <option value="">{{ __('inspection_round.any') }}</option>
        @foreach ($profiles as $profile)
            <option value="{{ $profile->sqid }}" @selected(old('asset_compliance_profile_id') === $profile->sqid)>{{ $profile->name }}</option>
        @endforeach
    </x-select-field>
    @if ($customers->isNotEmpty())
        <x-select-field name="customer_id" :label="__('inspection_round.customer')">
            <option value="">{{ __('inspection_round.any') }}</option>
            @foreach ($customers as $customer)
                <option value="{{ $customer->sqid }}" @selected(old('customer_id') === $customer->sqid)>{{ $customer->name }}</option>
            @endforeach
        </x-select-field>
    @endif
    <p class="text-xs text-muted">{{ __('inspection_round.form_hint') }}</p>
</x-modal>
