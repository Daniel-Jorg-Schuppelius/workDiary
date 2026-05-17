{{-- Variablen: $dutyPlan (Model|null), $isEdit --}}
@php
    $isEdit = $isEdit ?? false;
    $action = $isEdit
        ? route('duty-plans.update', $dutyPlan)
        : route('duty-plans.store');
@endphp

<x-modal
    :title="$isEdit ? __('Dienstplan bearbeiten') : __('Dienstplan anlegen')"
    :eyebrow="__('Dienstplanung')"
    icon="assignment"
    tone="primary"
    :action="$action"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')">

    @include('duty-plans._form', ['plan' => $dutyPlan ?? null])

    @if ($errors->any())
        <div class="alert alert-error text-sm">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
</x-modal>
