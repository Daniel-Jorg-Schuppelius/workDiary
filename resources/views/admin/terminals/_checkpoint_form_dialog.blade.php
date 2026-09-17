{{--
  Created on   : Thu Sep 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _checkpoint_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Check-in-Punkt anlegen (MVP-800). Variablen: $sites, $vehicles --}}
<x-modal
    :title="__('terminal.checkpoint.action.create')"
    :eyebrow="__('terminal.checkpoint.heading')"
    icon="qr_code_2"
    tone="primary"
    :action="route('admin.terminals.checkpoints.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('terminal.checkpoint.action.create')">

    <x-form-group :legend="__('terminal.checkpoint.heading')" icon="qr_code_2" tone="primary" cols="2">
        <x-input-field name="name" :label="__('terminal.field.name')" required maxlength="120" span="2" :value="old('name')" />
        <x-select-field name="kind" :label="__('terminal.checkpoint.field.kind')" required>
            @foreach (\App\Enums\Attendance\CheckpointKind::cases() as $kind)
                <option value="{{ $kind->value }}" @selected(old('kind', 'site') === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="site" :label="__('terminal.field.site')" :hint="__('terminal.checkpoint.help.site')">
            <option value="">{{ __('terminal.field.no_site') }}</option>
            @foreach ($sites as $site)
                <option value="{{ $site->sqid }}">{{ $site->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="vehicle" :label="__('terminal.checkpoint.field.vehicle')" :hint="__('terminal.checkpoint.help.vehicle')" span="2">
            <option value="">—</option>
            @foreach ($vehicles as $vehicle)
                <option value="{{ $vehicle->sqid }}">{{ $vehicle->displayName() }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>

    <x-form-group :legend="__('terminal.checkpoint.field.location_check')" :description="__('terminal.checkpoint.help.location_check')" icon="my_location" tone="ghost" cols="3">
        <x-input-field name="radius_m" type="number" min="10" max="5000" :label="__('terminal.checkpoint.field.radius')" :value="old('radius_m')" />
        <x-input-field name="latitude" type="number" step="0.0000001" min="-90" max="90" :label="__('terminal.checkpoint.field.latitude')" :value="old('latitude')" />
        <x-input-field name="longitude" type="number" step="0.0000001" min="-180" max="180" :label="__('terminal.checkpoint.field.longitude')" :value="old('longitude')" />
    </x-form-group>
</x-modal>
