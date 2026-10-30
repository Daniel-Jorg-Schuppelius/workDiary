{{--
  Created on   : Mon Oct 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _billing_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Abrechnung (Feature 185, MVP-1405): Serienrechnung bei lokaler
  Rechnungshoheit (Schalter + Vorlauf, Lauf = resale:draft-local); bei
  externer Hoheit nur der Hinweis, welches Programm die Rechnungen führt.
--}}
<x-modal
    :title="__('resale.auto_draft.dialog_title')"
    icon="event_repeat"
    tone="primary"
    size="md"
    :action="route('finance.resale.auto-draft.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('resale.auto_draft.save')"
>
    @if ($external)
        <div role="status" class="alert alert-info text-sm"><span>{{ __('resale.auto_draft.external_hint', ['program' => $mode->programName()]) }}</span></div>
    @else
        <p class="text-sm text-base-content/70">{{ __('resale.auto_draft.description') }}</p>
        <x-checkbox-field name="auto_local_drafts" tone="info"
                         :label="__('resale.auto_draft.enabled')"
                         :checked="(string) old('auto_local_drafts', $autoDrafts ? '1' : '0') === '1'" />
        <x-input-field name="lead_days" type="number" min="0" max="90" step="1" inputmode="numeric"
                       :label="__('resale.auto_draft.lead_days')"
                       :value="old('lead_days', $autoDraftLeadDays)"
                       :hint="__('resale.auto_draft.lead_days_hint')" />
    @endif
    {{-- Preisstaffel für direkt berechnete Domains (Feature 185, 2026-10-30): gilt unabhängig von der Rechnungshoheit. --}}
    <x-input-field name="domain_price_class" type="text" maxlength="64" list="resale-domain-price-classes" autocomplete="off"
                   :label="__('resale.auto_draft.domain_price_class')"
                   :value="old('domain_price_class', $domainPriceClass)"
                   :hint="__('resale.auto_draft.domain_price_class_hint', ['classes' => $domainPriceClasses === [] ? '—' : implode(', ', $domainPriceClasses)])" />
    <datalist id="resale-domain-price-classes">
        @foreach ($domainPriceClasses as $class)
            <option value="{{ $class }}"></option>
        @endforeach
    </datalist>
</x-modal>
