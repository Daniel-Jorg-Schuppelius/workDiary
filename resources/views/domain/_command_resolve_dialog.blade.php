{{--
  Created on   : Wed Nov 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _command_resolve_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: unklaren Provider-Befehl oder Konflikt nach Prüfung beim Anbieter abschließen (MVP-1614). --}}
@php /** @var \App\Models\Domain\DomainProviderCommand $command */ @endphp
<x-modal
    :title="__('domain.command.resolve_title')"
    :eyebrow="$command->command . ' · ' . $command->target"
    icon="fact_check"
    tone="warning"
    :action="route('domains.commands.resolve', $command)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('domain.command.resolve_save')">
    <p class="text-sm text-muted">{{ $command->reconciliationText() ?? __('domain.flash.command_unknown') }}</p>
    <x-form-group :legend="__('domain.command.resolve_title')" icon="fact_check" tone="warning" cols="1">
        <x-select-field name="outcome" :label="__('domain.command.outcome')" required>
            <option value="confirmed" @selected(old('outcome') === 'confirmed')>{{ __('domain.command.outcome_confirmed') }}</option>
            <option value="failed" @selected(old('outcome') === 'failed')>{{ __('domain.command.outcome_failed') }}</option>
        </x-select-field>
        <x-textarea-field name="reconciliation_note" :label="__('domain.command.resolve_note')" :hint="__('domain.command.resolve_note_hint')" required maxlength="200" rows="3">{{ old('reconciliation_note') }}</x-textarea-field>
    </x-form-group>
</x-modal>
