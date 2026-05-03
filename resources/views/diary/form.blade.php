@extends('layouts.app')
@section('title', ($isEdit ? __('Bearbeiten') : __('Neuer Eintrag')) . ' — WorkDiary')
@section('nav-title', $isEdit ? __('Eintrag bearbeiten') : __('Neuer Eintrag'))

@section('content')
    <div class="mx-auto w-full max-w-3xl rounded-box border border-base-300 bg-base-100 shadow-xs overflow-hidden">
        @include('diary._form_dialog')
    </div>
@endsection
