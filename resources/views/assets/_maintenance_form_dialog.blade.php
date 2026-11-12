{{--
  Created on   : Thu May 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _maintenance_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@php
    /** @var \App\Models\Asset\Asset $asset */
    /** @var \Illuminate\Support\Collection<int, \App\Models\ServiceTicket\SlaContract>|null $contractOptions */
    /** @var array<string, string>|null $dueActionOptions */
    $plan ??= null;
    $contractOptions ??= null;
    $dueActionOptions ??= null;
    $planKind = $plan?->interval_kind?->value;
    $planContractSqid = \App\Support\Sqid::encode(\App\Models\ServiceTicket\SlaContract::class, $plan?->sla_contract_id);
@endphp

<x-modal
    :title="$plan ? __('Wartungsplan bearbeiten') : __('Wartungsplan anlegen')"
    :eyebrow="$asset->name ?: $asset->asset_no"
    icon="event_repeat"
    tone="primary"
    size="md"
    :action="$plan ? route('assets.maintenance-plans.update', [$asset, $plan]) : route('assets.maintenance-plans.store', $asset)"
    :method="$plan ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$plan ? __('Speichern') : __('Anlegen')">

    <x-input-field name="label" :label="__('Bezeichnung')" required maxlength="180" :value="old('label', $plan?->label)" />

    <div class="grid gap-3 sm:grid-cols-2">
        <x-select-field name="interval_kind" :label="__('Intervall')">
            @foreach ($intervalKindOptions as $value => $label)
                <option value="{{ $value }}" @selected(old('interval_kind', $planKind ?? 'months') === $value)>{{ $label }}</option>
            @endforeach
        </x-select-field>
        <x-input-field type="number" name="interval_value" :label="__('Wert')" min="1" :value="old('interval_value', $plan?->interval_value ?? 6)" required />
        <x-input-field type="number" name="tolerance_days" :label="__('Toleranz (Tage)')" min="0" max="365" :value="old('tolerance_days', $plan?->tolerance_days ?? 7)" />
        <x-input-field type="date" name="next_due_on" :label="$plan ? __('Nächste Fälligkeit') : __('Erste Fälligkeit')" :value="old('next_due_on', $plan?->next_due_on?->toDateString())" />
    </div>

    @if ($contractOptions !== null || $dueActionOptions !== null)
        <div class="grid gap-3 sm:grid-cols-2">
            @if ($contractOptions !== null)
                <x-select-field name="sla_contract_id" :label="__('SLA-Vertrag')">
                    <option value="">{{ __('— Kein SLA —') }}</option>
                    @foreach ($contractOptions as $contract)
                        <option value="{{ $contract->sqid }}" @selected((string) old('sla_contract_id', $planContractSqid) === $contract->sqid)>{{ $contract->code }} — {{ $contract->label }}</option>
                    @endforeach
                </x-select-field>
            @endif
            @if ($dueActionOptions !== null)
                <x-select-field name="due_action" :label="__('Bei Fälligkeit')">
                    @foreach ($dueActionOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('due_action', $plan?->due_action?->value ?? \App\Enums\Asset\MaintenanceDueAction::None->value) === $value)>{{ $label }}</option>
                    @endforeach
                </x-select-field>
            @endif
            @if ($contractOptions !== null)
                <x-checkbox-field name="is_contractual" :label="__('Vertragspflicht')" span="2"
                                  :checked="(bool) old('is_contractual', $plan?->is_contractual ?? false)"
                                  :hint="__('Der Plan bildet einen Pflichttermin des gewählten SLA-Vertrags ab und erscheint dort als vertragspflichtige Wartung.')" />
            @endif
        </div>
    @endif
</x-modal>
