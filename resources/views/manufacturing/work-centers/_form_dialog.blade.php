{{--
  Created on   : Fri Jun 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Erwartet: $isDialog --}}
@php $isDialog = $isDialog ?? false; @endphp

<x-modal
    :title="__('manufacturing.capacity.add')"
    :eyebrow="__('manufacturing.capacity.title')"
    icon="precision_manufacturing"
    tone="primary"
    :action="route('work-centers.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Anlegen')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ route('work-centers.create') . '?dialog=1' }}">
    @endif

    <x-form-group :legend="__('Stammdaten')" icon="precision_manufacturing" tone="primary" cols="2">
        <x-input-field name="name" :label="__('manufacturing.capacity.work_center')" required maxlength="255" :value="old('name')" />
        <x-input-field name="code" :label="__('manufacturing.capacity.code')" maxlength="32" :value="old('code')" />
        <x-input-field name="capacity_minutes" type="number" :label="__('manufacturing.capacity.capacity') . ' (min)'" required min="1" :value="old('capacity_minutes', 480)" />
        <x-input-field name="setup_minutes" type="number" :label="__('manufacturing.capacity.setup') . ' (min)'" min="0" :value="old('setup_minutes', 0)" />
    </x-form-group>
</x-modal>
