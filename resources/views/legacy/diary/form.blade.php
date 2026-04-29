@extends('layouts.app')
@section('title', ($isEdit ? __('Legacy bearbeiten') : __('Legacy neuer Eintrag')) . ' — WorkDiary')
@section('nav-title', $isEdit ? __('Eintrag bearbeiten') : __('Eintrag neu'))

@section('content')
    <div class="mx-auto flex h-[calc(100dvh-11rem)] w-full max-w-2xl flex-col">
        <div class="min-h-0 flex-1 overflow-hidden rounded-box border border-base-300 bg-base-100 shadow-sm">
            <div class="h-full overflow-auto p-6 md:p-8">
            <div class="mb-6 flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.2em] text-base-content/60">{{ __('Legacy Eintrag') }}</p>
                    <h2 class="mt-2 font-['Space_Grotesk'] text-2xl font-bold text-base-content">{{ $isEdit ? __('Legacy-Eintrag bearbeiten') : __('Neuen Legacy-Eintrag anlegen') }}</h2>
                </div>
                <span class="badge badge-outline">{{ $isEdit ? __('Bearbeiten') : __('Neu') }}</span>
            </div>

            <form method="POST" action="{{ $isEdit ? route('legacy.diary.update', $entry) : route('legacy.diary.store') }}" class="space-y-6">
                @csrf
                @if ($isEdit)
                    @method('PUT')
                @endif

                @if (!empty($isAdmin) && $isAdmin)
                    <div>
                        <label for="user" class="mb-2 block text-sm font-medium text-base-content">{{ __('Mitarbeiter') }}</label>
                        <select id="user" name="user" class="select select-bordered select-sm w-full @error('user') ring-2 ring-error/30 @enderror">
                            @foreach (($users ?? collect()) as $user)
                                <option value="{{ $user->id }}" @selected((int) old('user', $entry?->user) === (int) $user->id)>{{ $user->uname }}</option>
                            @endforeach
                        </select>
                        @error('user')
                            <p class="mt-2 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                <div>
                    <label for="inhalt" class="mb-2 block text-sm font-medium text-base-content">{{ __('Inhalt') }}<span class="text-error">*</span></label>
                    <textarea id="inhalt" name="inhalt" rows="8" class="textarea textarea-bordered textarea-sm w-full @error('inhalt') ring-2 ring-error/30 @enderror" placeholder="{{ __('Beschreibe den Vorgang...') }}">{{ old('inhalt', $entry?->inhalt) }}</textarea>
                    @error('inhalt')
                        <p class="mt-2 text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="antwort" class="mb-2 block text-sm font-medium text-base-content">{{ __('Rückmeldung') }}</label>
                    <textarea id="antwort" name="antwort" rows="4" class="textarea textarea-bordered textarea-sm w-full">{{ old('antwort', $entry?->antwort) }}</textarea>
                </div>

                <div>
                    <label for="gelesen" class="mb-2 block text-sm font-medium text-base-content">{{ __('Status') }}<span class="text-error">*</span></label>
                    <select id="gelesen" name="gelesen" class="select select-bordered select-sm w-full @error('gelesen') ring-2 ring-error/30 @enderror">
                        <option value="2" @selected(old('gelesen', $entry?->gelesen ?? 2) == 2)>{{ __('Offen') }}</option>
                        <option value="3" @selected(old('gelesen', $entry?->gelesen) == 3)>{{ __('Problem') }}</option>
                        <option value="1" @selected(old('gelesen', $entry?->gelesen) == 1)>{{ __('Bestätigt') }}</option>
                        <option value="-1" @selected(old('gelesen', $entry?->gelesen) == -1)>{{ __('Erledigt') }}</option>
                    </select>
                    @error('gelesen')
                        <p class="mt-2 text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="von" class="mb-2 block text-sm font-medium text-base-content">{{ __('Von') }}</label>
                        <input id="von" name="von" type="datetime-local" value="{{ old('von', $entry?->von?->format('Y-m-d\\TH:i')) }}" class="input input-bordered input-sm w-full @error('von') ring-2 ring-error/30 @enderror">
                        @error('von')
                            <p class="mt-2 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="bis" class="mb-2 block text-sm font-medium text-base-content">{{ __('Bis') }}</label>
                        <input id="bis" name="bis" type="datetime-local" value="{{ old('bis', $entry?->bis?->format('Y-m-d\\TH:i')) }}" class="input input-bordered input-sm w-full @error('bis') ring-2 ring-error/30 @enderror">
                        @error('bis')
                            <p class="mt-2 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex items-center gap-3 rounded-box border border-base-300 bg-base-200 px-3 py-2">
                    <input id="sms" name="sms" type="checkbox" value="j" @checked(old('sms', $entry?->sms) === 'j') class="checkbox checkbox-sm">
                    <label for="sms" class="text-sm">{{ __('E-Mail-Hinweis senden') }}</label>
                </div>

                <div class="rounded-box border border-base-300 bg-base-200/60 p-4 text-xs text-base-content/70">
                    Felder mit <span class="text-error">*</span> sind erforderlich.
                </div>

                <div class="flex gap-3 pt-1">
                    <button type="submit" class=" btn btn-sm btn-primary">{{ $isEdit ? __('Speichern') : __('Eintrag anlegen') }}</button>
                    <a href="{{ $isEdit ? route('legacy.diary.show', $entry) : route('legacy.diary.index') }}" class=" btn btn-sm btn-ghost">{{ __('Abbrechen') }}</a>
                </div>
            </form>
            </div>
        </div>
    </div>
@endsection
