@extends('layouts.app')
@section('title', __('Mitglied anlegen'))
@section('content')
<div class="mx-auto max-w-lg py-8">
    <h1 class="text-2xl font-semibold mb-6">{{ __('Mitglied anlegen') }}</h1>
    <form method="POST" action="{{ route('org.members.store') }}" class="card bg-base-200 p-6 flex flex-col gap-4">
        @csrf
        @include('org.members._form', ['member' => null])
        <div class="flex gap-3 mt-2">
            <button type="submit" class="btn btn-primary">{{ __('Anlegen') }}</button>
            <a href="{{ route('org.members.index') }}" class="btn btn-ghost">{{ __('Abbrechen') }}</a>
        </div>
    </form>
</div>
@endsection
