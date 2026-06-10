@extends('layouts.app')

@section('title', __('Neue Betroffenenanfrage'))
@section('nav-title', __('Neue Betroffenenanfrage'))

@section('content')
    <x-index-page :subtitle="__('Eine neue Anfrage einer betroffenen Person erfassen.')">
        <x-slot:actions>
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                        :href="route('dataprotection.requests.index')"
                        show-label>{{ __('Zurück') }}</x-icon-btn>
        </x-slot:actions>

        @if ($errors->any())
            <div class="alert alert-error"><ul class="list-disc ml-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <x-card>
            <form method="post" action="{{ route('dataprotection.requests.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="label" for="type">{{ __('Art der Anfrage') }}</label>
                    <select id="type" name="type" class="select select-bordered w-full" required>
                        @foreach ($types as $t)
                            <option value="{{ $t->value }}" @selected(old('type') === $t->value)>{{ $t->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="channel">{{ __('Eingangskanal (optional)') }}</label>
                    <input id="channel" name="channel" class="input input-bordered w-full" value="{{ old('channel') }}" placeholder="email, post, telefon …">
                </div>
                <div>
                    <label class="label" for="subject">{{ __('Betroffene Person (Identität)') }}</label>
                    <textarea id="subject" name="subject" rows="2" class="textarea textarea-bordered w-full" required>{{ old('subject') }}</textarea>
                    <p class="text-xs text-base-content/60 mt-1">{{ __('Wird verschlüsselt gespeichert (Crypto-Shredding nach Aufbewahrung).') }}</p>
                </div>
                <div>
                    <label class="label" for="content">{{ __('Anliegen / Sachverhalt') }}</label>
                    <textarea id="content" name="content" rows="5" class="textarea textarea-bordered w-full" required>{{ old('content') }}</textarea>
                </div>
                <x-icon-btn icon="check" tone="primary" size="sm" type="submit" show-label>{{ __('Anlegen') }}</x-icon-btn>
            </form>
        </x-card>
    </x-index-page>
@endsection
