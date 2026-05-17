{{-- Legacy-Diary Form als Body. Erwartet: $entry, $isEdit, $isAdmin, $users, $isDialog (bool, optional) --}}
@php
    $isDialog = $isDialog ?? false;
    $isEdit = $isEdit ?? false;
    $dialogUrl = $isEdit
        ? route('legacy.diary.edit', $entry) . '?dialog=1'
        : route('legacy.diary.create') . '?dialog=1';
@endphp

@if ($isDialog)
    <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
@endif

@include('legacy.diary._form_fields')
