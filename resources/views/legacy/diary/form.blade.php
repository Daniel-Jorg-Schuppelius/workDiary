@extends('layouts.app')
@section('title', ($isEdit ? __('Legacy bearbeiten') : __('Legacy neuer Eintrag')) . ' — WorkDiary')
@section('nav-title', $isEdit ? __('Eintrag bearbeiten') : __('Eintrag neu'))

@section('content')
    <div class="mx-auto w-full max-w-2xl rounded-box border border-base-300 bg-base-100 shadow-sm overflow-hidden">
        @include('legacy.diary._form_dialog')
    </div>
@endsection
