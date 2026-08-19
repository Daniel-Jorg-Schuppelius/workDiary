{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@php
    use App\Enums\Sales\LeadSource;
    /** @var \App\Models\Lead|null $lead */
    $isEdit = $lead !== null;
    $action = $isEdit ? route('leads.update', $lead) : route('leads.store');
@endphp

<x-modal
    :title="$isEdit ? __('Lead bearbeiten') : __('Lead anlegen')"
    :eyebrow="__('Leads')"
    icon="person_search"
    tone="primary"
    :action="$action"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">

    <x-form-group cols="2">
        <x-input-field name="company" :label="__('Firma')" :value="old('company', $lead?->company)"
                       placeholder="{{ __('Muster GmbH') }}" />
        <x-input-field name="contact_name" :label="__('Ansprechpartner')" :value="old('contact_name', $lead?->contact_name)" />
        <x-input-field type="email" name="email" :label="__('E-Mail')" :value="old('email', $lead?->email)" />
        <x-input-field name="phone" :label="__('Telefon')" :value="old('phone', $lead?->phone)" />
        <x-select-field name="source" :label="__('Quelle')" required>
            @foreach (LeadSource::cases() as $source)
                <option value="{{ $source->value }}" @selected(old('source', $lead?->source?->value ?? 'other') === $source->value)>{{ $source->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="responsible_user" :label="__('Verantwortlich')">
            <option value="">{{ __('— niemand —') }}</option>
            @foreach ($users as $user)
                <option value="{{ $user->sqid }}" @selected(old('responsible_user', $lead?->responsible_user_id === $user->id ? $user->sqid : '') === $user->sqid)>{{ $user->name }}</option>
            @endforeach
        </x-select-field>
        <x-textarea-field name="interest" :label="__('Interesse / Bedarf')" rows="3" span="2">{{ old('interest', $lead?->interest) }}</x-textarea-field>
    </x-form-group>
</x-modal>
