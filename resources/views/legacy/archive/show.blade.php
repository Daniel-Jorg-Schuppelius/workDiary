@extends('layouts.app')
@section('title', __('Legacy Archiv') . ' #' . $entry->id . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Legacy') . ' / ' . __('Archiv'))

@section('content')
    <div class="mb-4">
        <a href="{{ route('legacy.archive.index') }}" class="link link-hover text-sm">← {{ __('Zur Archivliste') }}</a>
    </div>
    <div class="rounded-box border border-base-300 bg-base-100 p-6 shadow-xs">
        @include('legacy.archive._show_body', ['isDialog' => false])
    </div>
@endsection
