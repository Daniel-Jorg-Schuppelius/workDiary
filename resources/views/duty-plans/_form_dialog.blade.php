{{-- Variablen: $dutyPlan (Model|null), $isEdit --}}
@php
    $isEdit = $isEdit ?? false;
    $action = $isEdit
        ? route('duty-plans.update', $dutyPlan)
        : route('duty-plans.store');
@endphp

<x-dialog
    :title="$isEdit ? __('Dienstplan bearbeiten') : __('Dienstplan anlegen')"
    :eyebrow="__('Dienstplanung')"
    icon="📋"
    tone="primary">

    <form method="POST" action="{{ $action }}" class="space-y-4" data-entry-form>
        @csrf
        @if ($isEdit) @method('PUT') @endif

        @include('duty-plans._form', ['plan' => $dutyPlan ?? null])

        @if ($errors->any())
            <div class="alert alert-error text-sm">
                <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-3 pt-2">
            <button type="submit" class="btn btn-primary btn-sm">{{ $isEdit ? __('Speichern') : __('Anlegen') }}</button>
            <button type="button" class="btn btn-ghost btn-sm" data-entry-modal-close>{{ __('Abbrechen') }}</button>
        </div>
    </form>
</x-dialog>
