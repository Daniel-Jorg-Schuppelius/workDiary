{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _liquidity_scenario_form.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Kopf eines Liquiditätsszenarios (MVP-954). Erwartet: $scenario (oder null) --}}
@php($suffix = $scenario?->sqid ?? 'new')
<form method="POST" action="{{ $scenario !== null ? route('reports.accounting.liquidity-scenarios.update', $scenario) : route('reports.accounting.liquidity-scenarios.store') }}" class="grid gap-2 sm:grid-cols-3" data-entry-form>
    @csrf
    @if ($scenario !== null)
        @method('PUT')
    @endif
    {{-- Ohne Vorbereitungsrecht nur Anzeige. --}}
    <fieldset class="contents" @disabled(! $canEdit)>
    <x-input-field name="name" :id="'scenario-name-' . $suffix" :label="__('accounting.reports.scenario.field.name')" :value="$scenario?->name" required />
    <x-input-field name="receipt_delay_days" type="number" min="0" max="365" :id="'scenario-delay-' . $suffix" :label="__('accounting.reports.scenario.field.receipt_delay_days')" :value="$scenario?->receipt_delay_days" :hint="__('accounting.reports.scenario.hint.receipt_delay_days')" />
    <x-input-field name="inflow_change_percent" type="number" step="0.01" min="-100" :id="'scenario-in-' . $suffix" :label="__('accounting.reports.scenario.field.inflow_change_percent')" :value="$scenario?->inflow_change_percent" />
    <x-input-field name="outflow_change_percent" type="number" step="0.01" min="-100" :id="'scenario-out-' . $suffix" :label="__('accounting.reports.scenario.field.outflow_change_percent')" :value="$scenario?->outflow_change_percent" />
    <x-input-field name="note" :id="'scenario-note-' . $suffix" :label="__('accounting.reports.scenario.field.note')" :value="$scenario?->note" />
    <label class="flex items-center gap-2 self-end pb-2 text-sm">
        <input type="checkbox" name="is_including_investments" value="1" class="checkbox checkbox-sm" @checked($scenario?->is_including_investments)>
        {{ __('accounting.reports.scenario.field.is_including_investments') }}
    </label>
    </fieldset>
    @if ($canEdit)
        <div class="sm:col-span-3 flex justify-end">
            <x-button type="submit">{{ $scenario !== null ? __('accounting.reports.scenario.save') : __('accounting.reports.scenario.create') }}</x-button>
        </div>
    @endif
</form>
