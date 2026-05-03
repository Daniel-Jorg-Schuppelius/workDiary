{{-- Komplette Legacy-Diary Form als Body. Erwartet: $entry, $isEdit, $isAdmin, $users, $isDialog (bool, optional), $cancelUrl (optional) --}}
@php
    $isDialog = $isDialog ?? false;
    $action = $isEdit ? route('legacy.diary.update', $entry) : route('legacy.diary.store');
    $cancelUrl = $cancelUrl ?? ($isEdit ? route('legacy.diary.show', $entry) : route('legacy.diary.index'));
    $dialogUrl = $isEdit
        ? route('legacy.diary.edit', $entry) . '?dialog=1'
        : route('legacy.diary.create') . '?dialog=1';
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6" data-entry-form>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
    @endif

    @include('legacy.diary._form_fields')

    <div class="flex gap-3 pt-1">
        <button type="submit" class="btn btn-sm btn-primary">{{ $isEdit ? __('Speichern') : __('Eintrag anlegen') }}</button>
        @if ($isDialog)
            <button type="button" class="btn btn-sm btn-ghost" data-entry-modal-close>{{ __('Abbrechen') }}</button>
        @else
            <a href="{{ $cancelUrl }}" class="btn btn-sm btn-ghost">{{ __('Abbrechen') }}</a>
        @endif
    </div>
</form>
