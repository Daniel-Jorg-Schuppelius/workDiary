{{--
  Created on   : Mon Aug 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Menü anlegen (Partyservice-Branchenprofil). --}}
<x-modal
    :title="__('recipes.menu.action.create')"
    :eyebrow="__('recipes.menu.title')"
    icon="restaurant"
    tone="primary"
    :action="route('recipe-menus.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('recipes.menu.action.create')">

    <x-form-group :label="__('recipes.menu.field.name')" name="name">
        <input type="text" name="name" maxlength="160" required value="{{ old('name') }}" class="input input-bordered w-full">
    </x-form-group>
    <x-form-group :label="__('recipes.menu.field.event_date')" name="event_date">
        <input type="date" name="event_date" value="{{ old('event_date') }}" class="input input-bordered w-full">
    </x-form-group>
    <x-form-group :label="__('recipes.menu.field.guest_count')" name="guest_count">
        <input type="number" name="guest_count" min="1" max="100000" value="{{ old('guest_count') }}" class="input input-bordered w-40">
    </x-form-group>
</x-modal>
