@extends('layouts.app')
@section('title', ($isEdit ? __('Bearbeiten') : __('Neuer Eintrag')) . ' — WorkDiary')
@section('nav-title', $isEdit ? __('Eintrag bearbeiten') : __('Neuer Eintrag'))

@section('content')
    <div class="mx-auto flex h-[calc(100dvh-11rem)] w-full max-w-2xl flex-col">
        <div class="min-h-0 flex-1 overflow-hidden rounded-box border border-base-300 bg-base-100 shadow-sm">
            <div class="h-full overflow-auto p-6 md:p-8">
            <h2 class="mb-6 font-['Space_Grotesk'] text-2xl font-bold text-base-content">
                {{ $isEdit ? __('Eintrag bearbeiten') : __('Neuen Eintrag anlegen') }}
            </h2>

            <form
                method="POST"
                action="{{ $isEdit ? route('diary.update', $entry) : route('diary.store') }}"
                class="space-y-6"
            >
                @csrf
                @if ($isEdit)
                    @method('PUT')
                @endif

                {{-- Inhalt --}}
                <div>
                    <label for="content" class="mb-2 block text-sm font-medium text-base-content">{{ __('Inhalt') }}<span class="text-error">*</span></label>
                    <textarea
                        id="content"
                        name="content"
                        rows="8"
                        class="textarea textarea-bordered textarea-sm w-full @error('content') ring-2 ring-error/30 @enderror"
                        placeholder="{{ __('Beschreibe den Vorgang...') }}"
                    >{{ old('content', $entry?->content) }}</textarea>
                    @error('content')
                        <p class="mt-2 text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Rückmeldung --}}
                <div>
                    <label for="response" class="mb-2 block text-sm font-medium text-base-content">{{ __('Rückmeldung') }}</label>
                    <textarea
                        id="response"
                        name="response"
                        rows="4"
                        class="textarea textarea-bordered textarea-sm w-full"
                        placeholder="{{ __('Antwort oder Notiz (optional) ...') }}"
                    >{{ old('response', $entry?->response) }}</textarea>
                </div>

                {{-- Status --}}
                <div>
                    <label for="status" class="mb-2 block text-sm font-medium text-base-content">{{ __('Status') }}<span class="text-error">*</span></label>
                    <select
                        id="status"
                        name="status"
                        class="select select-bordered select-sm w-full @error('status') ring-2 ring-error/30 @enderror"
                    >
                        <option value="2" @selected(old('status', $entry?->status ?? 2) == 2)>{{ __('Offen') }}</option>
                        <option value="3" @selected(old('status', $entry?->status) == 3)>{{ __('Problem') }}</option>
                        <option value="1" @selected(old('status', $entry?->status) == 1)>{{ __('Bestätigt') }}</option>
                        <option value="-1" @selected(old('status', $entry?->status) == -1)>{{ __('Erledigt') }}</option>
                    </select>
                    @error('status')
                        <p class="mt-2 text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Zeitraum --}}
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="start_at" class="mb-2 block text-sm font-medium text-base-content">{{ __('Von') }}</label>
                        <input
                            id="start_at"
                            name="start_at"
                            type="datetime-local"
                            value="{{ old('start_at', $entry?->start_at?->format('Y-m-d\TH:i')) }}"
                            class="input input-bordered input-sm w-full @error('start_at') ring-2 ring-error/30 @enderror"
                        >
                        @error('start_at')
                            <p class="mt-2 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="end_at" class="mb-2 block text-sm font-medium text-base-content">{{ __('Bis') }}</label>
                        <input
                            id="end_at"
                            name="end_at"
                            type="datetime-local"
                            value="{{ old('end_at', $entry?->end_at?->format('Y-m-d\TH:i')) }}"
                            class="input input-bordered input-sm w-full @error('end_at') ring-2 ring-error/30 @enderror"
                        >
                        @error('end_at')
                            <p class="mt-2 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class=" btn btn-sm btn-primary">
                        {{ $isEdit ? __('Speichern') : __('Eintrag anlegen') }}
                    </button>
                    <a
                        href="{{ $isEdit ? route('diary.show', $entry) : route('diary.index') }}"
                        class=" btn btn-sm btn-ghost"
                    >{{ __('Abbrechen') }}</a>
                </div>
            </form>
            </div>
        </div>
    </div>
@endsection
