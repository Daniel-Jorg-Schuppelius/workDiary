{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _dispute_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Offenen Posten als strittig kennzeichnen (Feature 125, MVP-1559).
--}}
<x-modal
    :title="__('accounting.dispute.title')"
    :eyebrow="$item->document_reference"
    icon="gavel"
    :action="route('finance.accounting.open-items.dispute', $item)"
    method="POST"
    :submit-label="__('Speichern')"
>
    <p class="text-sm text-base-content/70">{{ __('accounting.dispute.hint') }}</p>

    <x-input-field name="reason" type="text" required minlength="3" maxlength="255"
                   :label="__('accounting.dispute.reason')"
                   :value="old('reason', '')" />
</x-modal>
