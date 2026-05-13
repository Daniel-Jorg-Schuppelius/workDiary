@extends('layouts.app')

@section('title', __('Organisation anlegen'))
@section('nav-title', __('Organisation anlegen'))

@section('content')
<div class="mx-auto max-w-lg py-8">
    <form method="POST" action="{{ route('admin.organizations.store') }}" class="card bg-base-200 p-6 flex flex-col gap-4">
        @csrf
        @include('admin.organizations._form', ['organization' => null])
        <div class="flex gap-3 mt-2">
            <button type="submit" class="btn btn-primary">{{ __('Anlegen') }}</button>
            <a href="{{ route('admin.organizations.index') }}" class="btn btn-ghost">{{ __('Abbrechen') }}</a>
        </div>
    </form>
</div>
@endsection
