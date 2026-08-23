{{--
  Created on   : Sat Aug 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _reopen_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Wiedereröffnung (Feature 125, MVP-677): hebt eine Festschreibung auf —
  deshalb Pflichtbegründung und Nachweis in der Hash-Kette.
--}}
<x-modal
    :title="__('accounting.closing.action.reopen')"
    :eyebrow="$period->starts_on->fdate() . ' – ' . $period->ends_on->fdate()"
    icon="lock_open"
    :action="route('finance.accounting.closing.reopen', $period)"
    method="POST"
    :submit-label="__('accounting.closing.action.reopen_submit')"
>
    <p class="text-sm text-base-content/70">{{ __('accounting.closing.reopen_hint') }}</p>

    <x-input-field name="reopen_reason" type="text" required maxlength="500"
                   :label="__('accounting.closing.field.reason')"
                   :value="old('reopen_reason', '')" />
</x-modal>
