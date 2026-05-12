@extends('layouts.app')
@section('title', __('Dienstplan anlegen'))
@section('content')
<div class="mx-auto max-w-lg py-8">
    <h1 class="text-2xl font-semibold mb-6">{{ __('Dienstplan anlegen') }}</h1>
    <form method="POST" action="{{ route('duty-plans.store') }}" class="card bg-base-200 p-6 flex flex-col gap-4">
        @csrf
        @include('duty-plans._form', ['plan' => null])
        <div class="flex gap-3 mt-2">
            <button type="submit" class="btn btn-primary">{{ __('Anlegen') }}</button>
            <a href="{{ route('duty-plans.index') }}" class="btn btn-ghost">{{ __('Abbrechen') }}</a>
        </div>
    </form>
</div>
@endsection
