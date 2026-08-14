{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Variablen: $queue, $isEdit, $teams, $slaContracts --}}
@php
    /** @var \App\Models\ServiceQueue $queue */
    /** @var bool $isEdit */
    $isEdit ??= $queue->exists;
    $action = $isEdit ? route('helpdesk.queues.update', $queue) : route('helpdesk.queues.store');
    $method = $isEdit ? 'PATCH' : 'POST';
    $title = $isEdit ? __('Queue bearbeiten') : __('Neue Queue');
@endphp

<x-modal
    :title="$title"
    :eyebrow="__('Helpdesk')"
    icon="inbox"
    tone="primary"
    size="md"
    :action="$action"
    :method="$method"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')">

    <x-form-group :legend="__('Queue')" icon="inbox" tone="primary" cols="2">
        <x-input-field name="name" :label="__('Name')" required maxlength="120" span="2" :value="old('name', $queue->name)" />
        <x-input-field name="purpose" :label="__('Zweck')" maxlength="500" span="2" :value="old('purpose', $queue->purpose)" />

        <x-select-field name="team_id" :label="__('Team')">
            <option value="">{{ __('— Kein Team —') }}</option>
            @foreach ($teams as $team)
                <option value="{{ $team->sqid }}" @selected((string) old('team_id', \App\Support\Sqid::encode(\App\Models\Team::class, $queue->team_id)) === $team->sqid)>{{ $team->name }}</option>
            @endforeach
        </x-select-field>

        <x-select-field name="default_sla_contract_id" :label="__('Standard-SLA')">
            <option value="">{{ __('— Kein SLA —') }}</option>
            @foreach ($slaContracts as $contract)
                <option value="{{ $contract->sqid }}" @selected((string) old('default_sla_contract_id', \App\Support\Sqid::encode(\App\Models\SlaContract::class, $queue->default_sla_contract_id)) === $contract->sqid)>{{ $contract->label }}</option>
            @endforeach
        </x-select-field>

        <x-select-field name="visibility" :label="__('Sichtbarkeit')" required>
            <option value="internal" @selected(old('visibility', $queue->visibility ?? 'internal') === 'internal')>{{ __('Intern') }}</option>
            <option value="portal" @selected(old('visibility', $queue->visibility) === 'portal')>{{ __('Kundenportal') }}</option>
        </x-select-field>

        <x-checkbox-field name="is_default" :label="__('Standard-Queue')" :checked="(bool) old('is_default', $queue->is_default)"
                          :hint="__('Neue Tickets ohne Zuordnung landen hier (genau eine je Organisation).')" />
    </x-form-group>
</x-modal>
