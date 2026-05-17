{{-- Variablen: $member (User|null), $isEdit, $roles --}}
@php
    $isEdit = $isEdit ?? false;
    $action = $isEdit
        ? route('org.members.update', $member)
        : route('org.members.store');
@endphp

<x-modal
    :title="$isEdit ? __('Mitglied bearbeiten') : __('Mitglied anlegen')"
    :eyebrow="__('Mitgliederverwaltung')"
    icon="group"
    tone="primary"
    :action="$action"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')">

    @include('org.members._form', ['member' => $member ?? null, 'roles' => $roles])

    @if ($errors->any())
        <div class="alert alert-error text-sm">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
</x-modal>
