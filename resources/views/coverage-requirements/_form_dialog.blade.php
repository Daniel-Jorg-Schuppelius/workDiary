{{--
  Created on   : Thu May 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Variables: $dutyPlan, $requirement (CoverageRequirement|null), $isEdit --}}
@php
    $isEdit = $isEdit ?? false;
    $action = $isEdit
        ? route('duty-plans.coverage.update', [$dutyPlan, $requirement])
        : route('duty-plans.coverage.store', $dutyPlan);
@endphp

<x-modal
    :title="$isEdit ? __('Soll-Besetzung bearbeiten') : __('Soll-Besetzung anlegen')"
    :eyebrow="$dutyPlan->title"
    icon="bar_chart"
    tone="primary"
    :action="$action"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')">

    @include('coverage-requirements._form', ['dutyPlan' => $dutyPlan, 'requirement' => $requirement])

    @if ($isEdit)
        <x-slot:footerExtra>
            <x-action-form :action="route('duty-plans.coverage.destroy', [$dutyPlan, $requirement])" method="DELETE"
                  :confirm="__('Wirklich löschen?')"
                  :confirm-label="__('Löschen')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Soll-Besetzung löschen') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
