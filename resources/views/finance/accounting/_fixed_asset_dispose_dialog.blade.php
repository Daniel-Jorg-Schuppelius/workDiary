{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _fixed_asset_dispose_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Abgang einer Anlage (Feature 133): Statuswechsel active → disposed mit
  Abgangsdatum. Die AfA des Abgangsjahres läuft bis zum Abgangsmonat.
  Variablen: $fixedAsset
--}}
<x-modal
    :title="__('accounting.fixed_assets.action.dispose')"
    :eyebrow="$fixedAsset->displayNo() . ' · ' . $fixedAsset->name"
    icon="logout"
    tone="warning"
    :action="route('finance.accounting.fixed-assets.dispose', $fixedAsset)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('accounting.fixed_assets.action.dispose_submit')">

    <p class="text-sm text-base-content/70">{{ __('accounting.fixed_assets.hint.dispose') }}</p>

    <x-input-field name="disposed_on" type="date" required
                   :label="__('accounting.fixed_assets.field.disposed_on')"
                   :value="old('disposed_on', now()->toDateString())" />

    <x-textarea-field name="note" :label="__('accounting.ledger.field.note')" rows="2" maxlength="2000"
                      :value="old('note', $fixedAsset->note)" />
</x-modal>
