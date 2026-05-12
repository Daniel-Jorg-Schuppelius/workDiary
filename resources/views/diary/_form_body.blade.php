{{-- Diary Form Body. Erwartet: $entry, $isEdit, $allTags, $selectedTagIds, $isDialog, $cancelUrl --}}
@php
    $isDialog = $isDialog ?? false;
    $action = $isEdit ? route('diary.update', $entry) : route('diary.store');
    $cancelUrl = $cancelUrl ?? ($isEdit ? route('diary.show', $entry) : route('diary.index'));
    $dialogUrl = $isEdit
        ? route('diary.edit', $entry) . '?dialog=1'
        : route('diary.create') . '?dialog=1';
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6" data-entry-form>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
    @endif

    @include('diary._form_fields')

    <div class="flex flex-wrap items-center gap-3 pt-2">
        <button type="submit" class="btn btn-sm btn-primary">
            {{ $isEdit ? __('Speichern') : __('Eintrag anlegen') }}
        </button>
        @if ($isDialog)
            <button type="button" class="btn btn-sm btn-ghost" data-entry-modal-close>{{ __('Abbrechen') }}</button>
        @else
            <a href="{{ $cancelUrl }}" class="btn btn-sm btn-ghost">{{ __('Abbrechen') }}</a>
        @endif
    </div>
</form>
