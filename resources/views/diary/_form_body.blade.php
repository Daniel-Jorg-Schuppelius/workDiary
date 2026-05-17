{{-- Diary Form Body. Erwartet: $entry, $isEdit, $allTags, $selectedTagIds, $isDialog --}}
@php
    $isDialog = $isDialog ?? false;
    $isEdit = $isEdit ?? false;
    $dialogUrl = $isEdit
        ? route('diary.edit', $entry) . '?dialog=1'
        : route('diary.create') . '?dialog=1';
@endphp

@if ($isDialog)
    <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
@endif

@include('diary._form_fields')
