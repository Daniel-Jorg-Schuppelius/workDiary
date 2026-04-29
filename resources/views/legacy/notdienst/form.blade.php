@extends('layouts.app')
@section('title', ($isEdit ? __('Notdienst bearbeiten') : __('Notdienst neu')) . ' — WorkDiary')
@section('nav-title', $isEdit ? __('Notdienst bearbeiten') : __('Notdienst neu'))

@section('content')
    <div class="mx-auto flex h-[calc(100dvh-11rem)] w-full max-w-xl flex-col">
        <div class="min-h-0 flex-1 overflow-hidden rounded-box border border-base-300 bg-base-100">
            <div class="h-full overflow-auto p-5">
            <form method="POST" action="{{ $isEdit ? route('legacy.notdienst.update', $item) : route('legacy.notdienst.store') }}" class="space-y-5">
                @csrf
                @if ($isEdit)
                    @method('PUT')
                @endif

                <div>
                    <label for="user" class="label text-sm font-semibold pb-1">{{ __('Mitarbeiter') }}</label>
                    <select id="user" name="user" class="select select-bordered select-sm w-full @error('user') select-error @enderror">
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((int) old('user', $item?->user) === (int) $user->id)>{{ $user->uname }}</option>
                        @endforeach
                    </select>
                    @error('user')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="von" class="label text-sm font-semibold pb-1">{{ __('Von') }}</label>
                        <input id="von" name="von" type="date" value="{{ old('von', $item?->von?->format('Y-m-d')) }}" class="input input-bordered input-sm w-full @error('von') input-error @enderror">
                        @error('von')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="bis" class="label text-sm font-semibold pb-1">{{ __('Bis') }}</label>
                        <input id="bis" name="bis" type="date" value="{{ old('bis', $item?->bis?->format('Y-m-d')) }}" class="input input-bordered input-sm w-full @error('bis') input-error @enderror">
                        @error('bis')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="flex gap-2 pt-1">
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Speichern') }}</button>
                    <a href="{{ route('legacy.notdienst.index') }}" class="btn btn-ghost btn-sm">{{ __('Abbrechen') }}</a>
                </div>
            </form>
            </div>
        </div>
    </div>
@endsection
