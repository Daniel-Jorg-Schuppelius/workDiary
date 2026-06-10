@extends('layouts.app')

@section('title', __('Neue Verarbeitungstätigkeit'))
@section('nav-title', __('Neue Verarbeitungstätigkeit'))

@section('content')
    <x-index-page :subtitle="__('Eine neue Verarbeitungstätigkeit im Verzeichnis anlegen.')">
        <x-slot:actions>
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                        :href="route('dataprotection.activities.index')"
                        show-label>{{ __('Zurück') }}</x-icon-btn>
        </x-slot:actions>

        @if ($errors->any())
            <div class="alert alert-error"><ul class="list-disc ml-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <x-card>
            <form method="post" action="{{ route('dataprotection.activities.store') }}" class="space-y-3">
                @csrf
                <div class="grid md:grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="name">{{ __('Bezeichnung') }}</label>
                        <input id="name" name="name" class="input input-bordered w-full" value="{{ old('name') }}" required>
                    </div>
                    <div>
                        <label class="label" for="controller_role">{{ __('Verantwortungsrolle') }}</label>
                        <select id="controller_role" name="controller_role" class="select select-bordered w-full">
                            @foreach ($roles as $r)
                                <option value="{{ $r->value }}" @selected(old('controller_role') === $r->value)>{{ $r->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="label" for="purpose">{{ __('Zweck der Verarbeitung') }}</label>
                    <textarea id="purpose" name="purpose" rows="2" class="textarea textarea-bordered w-full">{{ old('purpose') }}</textarea>
                </div>
                <div>
                    <label class="label" for="area">{{ __('Fachbereich (optional)') }}</label>
                    <input id="area" name="area" class="input input-bordered w-full" value="{{ old('area') }}">
                </div>

                @include('privacy.activities._payload_fields')

                <x-icon-btn icon="check" tone="primary" size="sm" type="submit" show-label>{{ __('Anlegen') }}</x-icon-btn>
            </form>
        </x-card>
    </x-index-page>
@endsection
