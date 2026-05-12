@extends('layouts.app')
@section('title', __('Mitglied bearbeiten'))
@section('content')
<div class="mx-auto max-w-lg py-8">
    <x-page-title :title="__('Mitglied bearbeiten')" :subtitle="$member->name" class="mb-6" />
    <form method="POST" action="{{ route('org.members.update', $member) }}" class="card bg-base-200 p-6 flex flex-col gap-4">
        @csrf @method('PUT')
        @include('org.members._form', ['member' => $member])
        <div class="flex gap-3 mt-2">
            <button type="submit" class="btn btn-primary">{{ __('Speichern') }}</button>
            <a href="{{ route('org.members.index') }}" class="btn btn-ghost">{{ __('Abbrechen') }}</a>
        </div>
    </form>
</div>
@endsection
