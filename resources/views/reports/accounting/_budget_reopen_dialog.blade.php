{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _budget_reopen_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Nachtrag zu einem freigegebenen Budget (MVP-983): öffnet es mit Begründung.
  Variablen: $year, $costCenter
--}}
<x-modal
    :title="__('accounting.budget.action.reopen')"
    :eyebrow="$year . ($costCenter ? ' · ' . $costCenter->code : '')"
    icon="edit_note"
    tone="warning"
    :action="route('reports.accounting.budget.reopen', array_filter(['year' => $year, 'cost_center' => $costCenter?->sqid]))"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('accounting.budget.action.reopen')">
    <p class="text-sm text-base-content/70">{{ __('accounting.budget.hint.reopen') }}</p>
    <x-textarea-field name="reopen_reason" required rows="3" maxlength="500"
                      :label="__('accounting.budget.field.reopen_reason')" :value="old('reopen_reason')" />
</x-modal>
