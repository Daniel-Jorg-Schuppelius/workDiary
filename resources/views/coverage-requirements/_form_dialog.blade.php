{{-- Variables: $dutyPlan, $requirement (CoverageRequirement|null), $isEdit --}}
@php
    $isEdit = $isEdit ?? false;
    $action = $isEdit
        ? route('duty-plans.coverage.update', [$dutyPlan, $requirement])
        : route('duty-plans.coverage.store', $dutyPlan);
@endphp

<x-dialog
    :title="$isEdit ? __('Soll-Besetzung bearbeiten') : __('Soll-Besetzung anlegen')"
    :eyebrow="$dutyPlan->title"
    icon="📊"
    tone="primary">

    <form method="POST" action="{{ $action }}" class="space-y-4" data-entry-form>
        @csrf
        @if ($isEdit) @method('PUT') @endif

        @include('coverage-requirements._form', ['dutyPlan' => $dutyPlan, 'requirement' => $requirement])

        <div class="flex flex-wrap items-center gap-3 pt-2">
            <button type="submit" class="btn btn-primary btn-sm">{{ $isEdit ? __('Speichern') : __('Anlegen') }}</button>
            <button type="button" class="btn btn-ghost btn-sm" data-entry-modal-close>{{ __('Abbrechen') }}</button>
        </div>
    </form>

    @if ($isEdit)
        <form method="POST" action="{{ route('duty-plans.coverage.destroy', [$dutyPlan, $requirement]) }}" class="mt-3"
              data-confirm-dialog
              data-confirm-message="{{ __('Wirklich löschen?') }}"
              data-confirm-label="{{ __('Löschen') }}">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-error btn-outline btn-sm">{{ __('Soll-Besetzung löschen') }}</button>
        </form>
    @endif
</x-dialog>
