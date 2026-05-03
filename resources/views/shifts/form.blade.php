@extends('layouts.app')
@section('title', ($isEdit ? __('Bereitschaft bearbeiten') : __('Neue Bereitschaft')) . ' — WorkDiary')
@section('nav-title', $isEdit ? __('Bereitschaft bearbeiten') : __('Neue Bereitschaft'))

@section('content')
    <div class="mx-auto w-full max-w-3xl rounded-box border border-base-300 bg-base-100 shadow-xs overflow-hidden">
        @include('shifts._form_dialog')
    </div>
@endsection
