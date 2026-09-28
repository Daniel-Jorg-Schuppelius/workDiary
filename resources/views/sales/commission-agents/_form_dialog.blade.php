{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Vermittler anlegen/bearbeiten (MVP-989). Variablen: $agent (CommissionAgent|null)
--}}
@php $isEdit = $agent !== null; @endphp
<x-modal
    :title="$isEdit ? __('commission.action.edit_agent') : __('commission.action.create_agent')"
    :eyebrow="__('commission.page.agents')"
    icon="handshake"
    tone="primary"
    :action="$isEdit ? route('commission-agents.update', $agent) : route('commission-agents.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('commission.action.save')">
    <x-input-field name="name" :label="__('commission.field.agent_name')" required maxlength="120" :value="old('name', $agent?->name)" />
    <x-input-field name="company" :label="__('commission.field.company')" maxlength="120" :value="old('company', $agent?->company)" />
    <x-input-field name="email" type="email" :label="__('commission.field.email')" maxlength="190" :value="old('email', $agent?->email)" />
    <x-input-field name="note" :label="__('commission.field.note')" maxlength="255" :value="old('note', $agent?->note)" />
    @if ($isEdit)
        <x-checkbox-field name="is_active" :label="__('commission.field.is_active')" :checked="(bool) old('is_active', $agent->is_active)" />
    @endif
</x-modal>
