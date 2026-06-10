@extends('layouts.app')
@section('title', __('Datenschutzvorfall melden'))
@section('nav-title', __('Datenschutzvorfall melden'))
@section('content')
    <x-index-page :subtitle="__('Mit dem Anlegen startet die 72-Stunden-Frist zur Meldung an die Aufsichtsbehörde (Art. 33).')">
        <x-slot:actions>
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                        :href="route('dataprotection.incidents.index')"
                        show-label>{{ __('Zurück') }}</x-icon-btn>
        </x-slot:actions>

        @if ($errors->any())<div class="alert alert-error"><ul class="list-disc ml-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

        <x-card class="max-w-2xl">
            <form method="post" action="{{ route('dataprotection.incidents.store') }}" class="space-y-3">
                @csrf
                <div class="grid md:grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="type">{{ __('Art des Vorfalls') }}</label>
                        <select id="type" name="type" class="select select-bordered w-full">
                            @foreach ($types as $t)<option value="{{ $t->value }}" @selected(old('type') === $t->value)>{{ $t->label() }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="occurred_at">{{ __('Zeitpunkt des Ereignisses') }}</label>
                        <input id="occurred_at" name="occurred_at" type="datetime-local" class="input input-bordered w-full" value="{{ old('occurred_at') }}">
                    </div>
                </div>
                <div>
                    <label class="label" for="summary">{{ __('Sachverhalt') }}</label>
                    <textarea id="summary" name="summary" rows="4" class="textarea textarea-bordered w-full" required>{{ old('summary') }}</textarea>
                    <p class="text-xs text-base-content/60 mt-1">{{ __('Wird verschlüsselt gespeichert.') }}</p>
                </div>
                <div>
                    <label class="label" for="affected">{{ __('Betroffene Daten / Personen / Systeme') }}</label>
                    <textarea id="affected" name="affected" rows="3" class="textarea textarea-bordered w-full">{{ old('affected') }}</textarea>
                </div>
                <button class="btn btn-error">{{ __('Vorfall anlegen') }}</button>
            </form>
        </x-card>
    </x-index-page>
@endsection
