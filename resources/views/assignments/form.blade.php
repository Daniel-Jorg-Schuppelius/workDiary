@extends('layouts.app')
@section('title', ($isEdit ? __('Notdienst bearbeiten') : __('Neuer Notdienst')) . ' — WorkDiary')
@section('nav-title', $isEdit ? __('Notdienst bearbeiten') : __('Neuer Notdienst'))

@section('content')
    <div class="mx-auto w-full max-w-3xl rounded-box border border-base-300 bg-base-100 shadow-xs overflow-hidden">
        @include('assignments._form_dialog')
    </div>
@endsection
