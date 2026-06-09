@extends('layouts.app')

@section('title', __('Neue Betroffenenanfrage'))

@section('content')
    <div class="p-4 max-w-2xl space-y-4">
        <h1 class="text-xl font-semibold">{{ __('Neue Betroffenenanfrage') }}</h1>

        @if ($errors->any())
            <div class="alert alert-error"><ul class="list-disc ml-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

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
            <button class="btn btn-primary">{{ __('Anlegen') }}</button>
        </form>
    </div>
@endsection
