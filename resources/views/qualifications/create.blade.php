@extends('layouts.app')
@section('title', __('Qualifikation anlegen'))
@section('nav-title', __('Qualifikation anlegen'))
@section('content')
<div class="mx-auto max-w-lg py-8">
    <form method="POST" action="{{ route('qualifications.store') }}" class="card bg-base-200 p-6 flex flex-col gap-4">
        @csrf
        @include('qualifications._form', ['qualification' => null])
        <div class="flex gap-3 mt-2">
            <button type="submit" class="btn btn-primary">{{ __('Anlegen') }}</button>
            <a href="{{ route('qualifications.index') }}" class="btn btn-ghost">{{ __('Abbrechen') }}</a>
        </div>
    </form>
</div>
@endsection
