@extends('layouts.app')
@section('title', __('Qualifikation bearbeiten'))
@section('content')
<div class="mx-auto max-w-lg py-8">
    <x-page-title :title="__('Qualifikation bearbeiten')" :subtitle="$qualification->name" class="mb-6" />
    <form method="POST" action="{{ route('qualifications.update', $qualification) }}" class="card bg-base-200 p-6 flex flex-col gap-4">
        @csrf @method('PUT')
        @include('qualifications._form', ['qualification' => $qualification])
        <div class="flex gap-3 mt-2">
            <button type="submit" class="btn btn-primary">{{ __('Speichern') }}</button>
            <a href="{{ route('qualifications.index') }}" class="btn btn-ghost">{{ __('Abbrechen') }}</a>
        </div>
    </form>
</div>
@endsection
