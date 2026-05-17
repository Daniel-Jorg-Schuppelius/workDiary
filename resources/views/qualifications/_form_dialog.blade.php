{{-- Variablen: $qualification (Model|null), $isEdit --}}
@php
    $isEdit = $isEdit ?? false;
    $action = $isEdit
        ? route('qualifications.update', $qualification)
        : route('qualifications.store');
@endphp

<x-modal
    :title="$isEdit ? __('Qualifikation bearbeiten') : __('Qualifikation anlegen')"
    :eyebrow="__('Qualifikationen')"
    icon="school"
    tone="primary"
    :action="$action"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')">

    @include('qualifications._form', ['qualification' => $qualification ?? null])

    @if ($errors->any())
        <div class="alert alert-error text-sm">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    @if ($isEdit)
        <x-slot:footerExtra>
            <form method="POST" action="{{ route('qualifications.destroy', $qualification) }}"
                  data-confirm-dialog
                  data-confirm-message="{{ __('Wirklich löschen?') }}"
                  data-confirm-label="{{ __('Löschen') }}">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-error btn-outline btn-sm gap-2">
                    <x-icon name="delete" /> {{ __('Qualifikation löschen') }}
                </button>
            </form>
        </x-slot:footerExtra>
    @endif
</x-modal>
