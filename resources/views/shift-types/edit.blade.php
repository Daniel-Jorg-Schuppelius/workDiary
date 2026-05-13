@extends('layouts.app')
@section('title', __('Schichttyp bearbeiten'))
@section('nav-title', __('Schichttyp bearbeiten'))

@section('content')
    <div class="space-y-6">
        <form method="POST" action="{{ route('shift-types.update', $type) }}" class="space-y-4 max-w-2xl">
            @csrf @method('PUT')
            @include('shift-types._form', ['type' => $type])
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('Speichern') }}</button>
                <a href="{{ route('shift-types.index') }}" class="btn btn-ghost btn-sm">{{ __('Abbrechen') }}</a>
            </div>
        </form>
    </div>
@endsection
