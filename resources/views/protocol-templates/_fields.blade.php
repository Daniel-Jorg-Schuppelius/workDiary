{{--
  Created on   : Fri Sep 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _fields.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Gemeinsame Felder: Name, Beschreibung, Zuordnung, Gültigkeit (MVP-901). --}}
<x-input-field name="name" :label="__('protocol.template.name')" :value="old('name', $template?->name)" required maxlength="180" />
<x-textarea-field name="description" :label="__('protocol.field.description')" :value="old('description', $template?->description)" rows="2" />
<x-select-field name="entry_type_id" :label="__('protocol.template.entry_type')" :hint="__('protocol.template.target_hint')">
    <option value="">{{ __('protocol.template.any') }}</option>
    @foreach ($entryTypes as $entryType)
        <option value="{{ $entryType->sqid }}" @selected(old('entry_type_id', $template?->entryType?->sqid) === $entryType->sqid)>{{ $entryType->label }}</option>
    @endforeach
</x-select-field>
<x-select-field name="customer_id" :label="__('protocol.template.customer')">
    <option value="">{{ __('protocol.template.any') }}</option>
    @foreach ($customers as $customer)
        <option value="{{ $customer->sqid }}" @selected(old('customer_id', $template?->customer?->sqid) === $customer->sqid)>{{ $customer->name }}</option>
    @endforeach
</x-select-field>
<x-date-range from-name="valid_from" to-name="valid_until" :from="old('valid_from', $template?->valid_from?->toDateString())" :to="old('valid_until', $template?->valid_until?->toDateString())" :label="__('protocol.template.validity')" />
