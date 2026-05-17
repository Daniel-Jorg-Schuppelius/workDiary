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
            <form method="POST" action="{{ route('duty-plans.coverage.destroy', [$dutyPlan, $requirement]) }}"
                  data-confirm-dialog
                  data-confirm-message="{{ __('Wirklich löschen?') }}"
                  data-confirm-label="{{ __('Löschen') }}">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-error btn-outline btn-sm gap-2">
                    <x-icon name="delete" /> {{ __('Soll-Besetzung löschen') }}
                </button>
            </form>
        </x-slot:footerExtra>
    @endif
</x-modal>
