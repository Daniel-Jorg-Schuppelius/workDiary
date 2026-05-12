{{-- Variablen: $member (User|null), $isEdit, $roles --}}
@php
    $isEdit = $isEdit ?? false;
    $action = $isEdit
        ? route('org.members.update', $member)
        : route('org.members.store');
@endphp

<x-dialog
    :title="$isEdit ? __('Mitglied bearbeiten') : __('Mitglied anlegen')"
    :eyebrow="__('Mitgliederverwaltung')"
    icon="👥"
    tone="primary">

    <form method="POST" action="{{ $action }}" class="space-y-4" data-entry-form>
        @csrf
        @if ($isEdit) @method('PUT') @endif

        @include('org.members._form', ['member' => $member ?? null, 'roles' => $roles])

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
