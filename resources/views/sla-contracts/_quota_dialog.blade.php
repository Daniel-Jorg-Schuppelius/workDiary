{{--
  Created on   : Wed Nov 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _quota_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Inklusivzeit-Kontingent am SLA-Vertrag (Feature 010): Zeitraum, Stunden, Warnschwelle, Pauschale, Mehrstundensatz. --}}
<x-modal
    :title="$quota ? __('sla.quota.edit') : __('sla.quota.create')"
    :eyebrow="$contract->code"
    icon="data_usage"
    tone="primary"
    :action="$quota ? route('sla-contracts.quotas.update', [$contract, $quota]) : route('sla-contracts.quotas.store', $contract)"
    :method="$quota ? 'PATCH' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">
    <x-form-group :legend="__('sla.quota.legend')" icon="data_usage" tone="primary" cols="2">
        <x-select-field name="period_kind" :label="__('sla.quota.period')" required>
            @foreach (\App\Enums\ServiceTicket\SlaQuotaPeriod::cases() as $period)
                <option value="{{ $period->value }}" @selected(old('period_kind', $quota?->period_kind?->value ?? 'month') === $period->value)>{{ $period->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="included_hours" type="number" min="0" step="0.25" inputmode="decimal" required
                       :label="__('sla.quota.included_hours')"
                       :value="old('included_hours', $quota !== null ? round($quota->included_minutes / 60, 2) : '')" />
        <x-input-field name="warn_threshold_pct" type="number" min="1" max="100" step="1" inputmode="numeric" required
                       :label="__('sla.quota.warn_threshold')"
                       :hint="__('sla.quota.warn_threshold_hint')"
                       :value="old('warn_threshold_pct', $quota?->warn_threshold_pct ?? 80)" />
    </x-form-group>
    <x-form-group :legend="__('sla.quota.amounts')" icon="payments" tone="primary" cols="2" :description="__('sla.quota.amounts_hint')">
        <x-input-field name="flat_fee" type="number" min="0" step="0.01" inputmode="decimal"
                       :label="__('sla.quota.flat_fee')"
                       :value="old('flat_fee', $quota?->flat_fee)" />
        <x-input-field name="overage_rate" type="number" min="0" step="0.01" inputmode="decimal"
                       :label="__('sla.quota.overage_rate')"
                       :value="old('overage_rate', $quota?->overage_rate)" />
    </x-form-group>
</x-modal>
