@extends('layouts.app')
@section('title', ($tag ? __('Tag bearbeiten') : __('Neuer Tag')) . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', $tag ? __('Tag bearbeiten') : __('Neuer Tag'))

@section('content')
<div class="mx-auto w-full max-w-md">
    <form method="POST" action="{{ $tag ? route('tags.update', $tag) : route('tags.store') }}"
        class="rounded-box border border-base-300 bg-base-100 p-6 shadow-sm space-y-4">
        @csrf
        @if ($tag) @method('PUT') @endif

        <div>
            <label for="name" class="mb-1 block text-sm font-medium">{{ __('Name') }}</label>
            <input id="name" name="name" type="text" required maxlength="60" class="input input-bordered input-sm w-full"
                value="{{ old('name', $tag?->name) }}">
            @error('name')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="color" class="mb-1 block text-sm font-medium">{{ __('Farbe') }}</label>
            <input id="color" name="color" type="color" value="{{ old('color', $tag?->color ?? '#3b82f6') }}"
                class="input input-bordered input-sm h-9 w-16 p-1">
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="btn btn-primary btn-sm">{{ __('Speichern') }}</button>
            <a href="{{ route('tags.index') }}" class="btn btn-ghost btn-sm">{{ __('Abbrechen') }}</a>
        </div>
    </form>
</div>
@endsection
