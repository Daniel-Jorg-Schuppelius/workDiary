{{-- Variablen: $qualification (Model|null), $isEdit --}}
@php
    $isEdit = $isEdit ?? false;
    $action = $isEdit
        ? route('qualifications.update', $qualification)
        : route('qualifications.store');
@endphp

<x-dialog
    :title="$isEdit ? __('Qualifikation bearbeiten') : __('Qualifikation anlegen')"
    :eyebrow="__('Qualifikationen')"
    icon="🎓"
    tone="primary">

    <form method="POST" action="{{ $action }}" class="space-y-4" data-entry-form>
        @csrf
        @if ($isEdit) @method('PUT') @endif

        @include('qualifications._form', ['qualification' => $qualification ?? null])

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

    @if ($isEdit)
        <form method="POST" action="{{ route('qualifications.destroy', $qualification) }}" class="mt-3"
              data-confirm-dialog
              data-confirm-message="{{ __('Wirklich löschen?') }}"
              data-confirm-label="{{ __('Löschen') }}">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-error btn-outline btn-sm">{{ __('Qualifikation löschen') }}</button>
        </form>
    @endif
</x-dialog>
