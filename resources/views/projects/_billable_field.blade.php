{{--
  Abrechenbar-Override: Tri-State erben/ja/nein (Spalte projects.billable,
  null = erben von Parent-Kette → Kunde, s. effectiveBillable()).

  Eigenes Partial wie _weather_field.blade.php: _form_dialog.blade.php liegt
  nahe der Backtracking-Schwelle des Blade-ComponentTagCompilers.
--}}
@php
    $billableCurrent = $project?->billable === null ? '' : ($project->billable ? '1' : '0');
    $billableValue = (string) old('billable', $billableCurrent);
    // Anzeige, was „Erben" aktuell bedeutet: Parent-Kette → Kunde → ja. Bei
    // Neuanlage steht der Kunde noch nicht fest → generisches Label.
    $billableInheritLabel = $project === null
        ? __('Erben (Kunde bzw. übergeordnetes Projekt)')
        : __('Erben (aktuell: :value)', ['value' => ($project->parent?->effectiveBillable() ?? $project->customer?->billable ?? true) ? __('Ja') : __('Nein')]);
@endphp
<x-form-group :legend="__('Abrechenbarkeit')" icon="payments" tone="warning">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Abrechenbar') }}</label>
        <select name="billable" class="select select-bordered w-full">
            <option value="" @selected($billableValue === '')>{{ $billableInheritLabel }}</option>
            <option value="1" @selected($billableValue === '1')>{{ __('Ja') }}</option>
            <option value="0" @selected($billableValue === '0')>{{ __('Nein') }}</option>
        </select>
        <p class="text-xs text-base-content/60">{{ __('Gilt als Vorgabe für neue Zeiteinträge dieses Projekts; nicht abrechenbare Projekte erzeugen keinen Umsatz. Erben nutzt die Einstellung des übergeordneten Projekts bzw. des Kunden.') }}</p>
        @error('billable')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
</x-form-group>
