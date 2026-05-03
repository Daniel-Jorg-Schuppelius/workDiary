@extends('layouts.app')
@section('title', ($project ? __('Projekt bearbeiten') : __('Neues Projekt')) . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', $project ? __('Projekt bearbeiten') : __('Neues Projekt'))

@section('content')
<div class="mx-auto w-full max-w-2xl rounded-box border border-base-300 bg-base-100 shadow-xs overflow-hidden">
    @include('projects._form_dialog', ['project' => $project, 'isDialog' => false])
</div>
@endsection
