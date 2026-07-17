{{--
  Created on   : Sun Jun 14 2026
  Author       : Daniel Jörg Schuppelius
  License      : AGPL-3.0-or-later

  Anlage-Dialog für einen DATEV-Buchungsstapel (Feature 045, Priorität 2):
  Zeitraum (x-date-range) + optionale Spesen-Auswahl. Erzeugt einen Draft;
  Finalisierung erfolgt nach der Vorschau/Preflight.
--}}
<x-modal
    :title="__('finance.datev.dialog.create_title')"
    icon="account_tree"
    tone="primary"
    size="lg"
    :action="route('finance.datev.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('finance.datev.action.create')"
>
    <div class="text-sm text-base-content/70">{{ __('finance.datev.dialog.create_hint') }}</div>

    <div class="fieldset">
        <x-date-range :label="__('finance.datev.field.period')"
                      form-control
                      from-name="from" to-name="to"
                      :from="old('from', $defaultFrom)" :to="old('to', $defaultTo)"
                      required />
        <p class="mt-1 text-xs text-base-content/60">{{ __('finance.datev.hint.period_sources') }}</p>
    </div>

    <div class="fieldset">
        <label class="label cursor-pointer justify-start gap-3">
            <input type="hidden" name="include_expenses" value="0">
            <input type="checkbox" name="include_expenses" value="1"
                   @checked(old('include_expenses')) class="checkbox checkbox-sm">
            <span class="label-text">{{ __('finance.datev.field.include_expenses') }}</span>
        </label>
        <p class="mt-1 text-xs text-base-content/60">{{ __('finance.datev.hint.include_expenses') }}</p>
    </div>

    {{-- Storno-Übergabe (MVP-334): Generalumkehr für bereits übergebene, stornierte Belege. --}}
    <div class="fieldset">
        <label class="label cursor-pointer justify-start gap-3">
            <input type="hidden" name="include_reversals" value="0">
            <input type="checkbox" name="include_reversals" value="1"
                   @checked(old('include_reversals')) class="checkbox checkbox-sm">
            <span class="label-text">{{ __('finance.datev.field.include_reversals') }}</span>
        </label>
        <p class="mt-1 text-xs text-base-content/60">{{ __('finance.datev.hint.include_reversals') }}</p>
    </div>

    <x-validation-errors />
</x-modal>
