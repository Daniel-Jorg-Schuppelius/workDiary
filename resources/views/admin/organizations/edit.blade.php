@extends('layouts.app')

@section('title', __('Organisation bearbeiten'))

@section('content')
<div class="mx-auto max-w-lg py-8">
    <h1 class="text-2xl font-semibold mb-6">{{ __('Organisation bearbeiten') }}: {{ $organization->name }}</h1>

    <form method="POST" action="{{ route('admin.organizations.update', $organization) }}" class="card bg-base-200 p-6 flex flex-col gap-4">
        @csrf @method('PUT')
        @include('admin.organizations._form', ['organization' => $organization])
        <div class="flex gap-3 mt-2">
            <button type="submit" class="btn btn-primary">{{ __('Speichern') }}</button>
            <a href="{{ route('admin.organizations.index') }}" class="btn btn-ghost">{{ __('Abbrechen') }}</a>
        </div>
    </form>
</div>
@endsection
