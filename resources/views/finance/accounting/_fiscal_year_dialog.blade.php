{{--
  Created on   : Fri Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _fiscal_year_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Geschäftsjahr anlegen (Feature 125, MVP-671): erzeugt zwölf Monatsperioden
  in einem Zug — ein Jahr mit Lücken hätte Buchungsdaten ohne Ort.
--}}
<x-modal
    :title="__('accounting.ledger.action.add_fiscal_year')"
    icon="calendar_month"
    :action="route('finance.accounting.fiscal-years.store')"
    method="POST"
    :submit-label="__('Speichern')"
>
    <x-input-field name="starts_on" type="date" required
                   :label="__('accounting.ledger.field.fiscal_year_starts_on')"
                   :hint="__('accounting.ledger.hint.fiscal_year_starts_on')"
                   :value="old('starts_on', $suggestedStart)" />

    <x-input-field name="label" type="text" maxlength="32"
                   :label="__('accounting.ledger.field.fiscal_year_label')"
                   :hint="__('accounting.ledger.hint.fiscal_year_label')"
                   :value="old('label', '')" />
</x-modal>
