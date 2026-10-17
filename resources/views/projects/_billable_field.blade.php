{{--
  Created on   : Thu Jul 30 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _billable_field.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Abrechenbar-Override: Tri-State erben/ja/nein (Spalte projects.billable,
  null = erben von Parent-Kette → Kunde, s. effectiveBillable()).

  Eigenes Partial wie _weather_field.blade.php: _form_dialog.blade.php liegt
  nahe der Backtracking-Schwelle des Blade-ComponentTagCompilers.
--}}
@php
    $billableCurrent = $project?->billable === null ? '' : ($project->billable ? '1' : '0');
    $billableValue = (string) old('billable', $billableCurrent);
    // Anzeige, was „Erben" aktuell bedeutet (Parent-Kette → Kunde, kundenlos
    // = nein). Bei Neuanlage steht der Kunde noch nicht fest → generisches Label.
    $billableInheritLabel = $project === null
        ? __('Erben (Kunde bzw. übergeordnetes Projekt)')
        : __('Erben (aktuell: :value)', ['value' => $project->inheritedBillable() ? __('Ja') : __('Nein')]);
@endphp
<x-form-group :legend="__('Abrechenbarkeit')" icon="payments" tone="warning">
    <x-select-field name="billable"
                    :label="__('Abrechenbar')"
                    :hint="__('Gilt als Vorgabe für neue Zeiteinträge dieses Projekts; nicht abrechenbare Projekte erzeugen keinen Umsatz. Erben nutzt die Einstellung des übergeordneten Projekts bzw. des Kunden; interne Projekte ohne Kunden sind nicht abrechenbar.')">
        <option value="" @selected($billableValue === '')>{{ $billableInheritLabel }}</option>
        <option value="1" @selected($billableValue === '1')>{{ __('Ja') }}</option>
        <option value="0" @selected($billableValue === '0')>{{ __('Nein') }}</option>
    </x-select-field>
</x-form-group>
