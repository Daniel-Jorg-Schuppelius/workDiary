@extends('layouts.app')
@section('title', __('Neue TOM'))
@section('nav-title', __('Neue technische/organisatorische Maßnahme'))
@section('content')
    <x-index-page :subtitle="__('Neue technische oder organisatorische Maßnahme im Katalog anlegen.')">
        <x-slot:actions>
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                        :href="route('dataprotection.tom.index')"
                        show-label>{{ __('Zurück') }}</x-icon-btn>
        </x-slot:actions>

        @if ($errors->any())<div class="alert alert-error"><ul class="list-disc ml-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

        <x-card class="max-w-2xl">
            <form method="post" action="{{ route('dataprotection.tom.store') }}" class="space-y-3">
                @csrf
                <div class="grid md:grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="name">{{ __('Bezeichnung') }}</label>
                        <input id="name" name="name" class="input input-bordered w-full" value="{{ old('name') }}" required>
                    </div>
                    <div>
                        <label class="label" for="category">{{ __('Maßnahmenbereich') }}</label>
                        <select id="category" name="category" class="select select-bordered w-full">
                            @foreach ($categories as $c)<option value="{{ $c->value }}" @selected(old('category') === $c->value)>{{ $c->label() }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="label" for="description">{{ __('Beschreibung') }}</label>
                    <textarea id="description" name="description" rows="3" class="textarea textarea-bordered w-full">{{ old('description') }}</textarea>
                </div>
                <div>
                    <label class="label" for="addressed_risks">{{ __('Adressierte Risiken') }}</label>
                    <textarea id="addressed_risks" name="addressed_risks" rows="2" class="textarea textarea-bordered w-full">{{ old('addressed_risks') }}</textarea>
                </div>
                <div>
                    <label class="label" for="evidence">{{ __('Nachweise (Richtlinien, Protokolle, Zertifikate …)') }}</label>
                    <textarea id="evidence" name="evidence" rows="2" class="textarea textarea-bordered w-full">{{ old('evidence') }}</textarea>
                </div>
                <button class="btn btn-primary">{{ __('Anlegen') }}</button>
            </form>
        </x-card>
    </x-index-page>
@endsection
