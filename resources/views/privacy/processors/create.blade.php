@extends('layouts.app')
@section('title', __('Neuer Dienstleister'))
@section('content')
    <div class="p-4 max-w-2xl space-y-4">
        <h1 class="text-xl font-semibold">{{ __('Neuer Dienstleister') }}</h1>
        @if ($errors->any())<div class="alert alert-error"><ul class="list-disc ml-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

        <form method="post" action="{{ route('dataprotection.processors.store') }}" class="space-y-3">
            @csrf
            <div class="grid md:grid-cols-2 gap-3">
                <div>
                    <label class="label" for="name">{{ __('Name') }}</label>
                    <input id="name" name="name" class="input input-bordered w-full" value="{{ old('name') }}" required>
                </div>
                <div>
                    <label class="label" for="role">{{ __('Rolle') }}</label>
                    <select id="role" name="role" class="select select-bordered w-full">
                        @foreach ($roles as $r)<option value="{{ $r->value }}" @selected(old('role') === $r->value)>{{ $r->label() }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="contact">{{ __('Kontakt') }}</label>
                    <input id="contact" name="contact" class="input input-bordered w-full" value="{{ old('contact') }}">
                </div>
                <div>
                    <label class="label" for="location">{{ __('Verarbeitungsort') }}</label>
                    <input id="location" name="location" class="input input-bordered w-full" value="{{ old('location') }}">
                </div>
            </div>
            <label class="flex items-center gap-2">
                <input type="hidden" name="third_country" value="0">
                <input type="checkbox" name="third_country" value="1" class="checkbox" @checked(old('third_country'))> {{ __('Drittlandtransfer') }}
            </label>
            <div>
                <label class="label" for="notes">{{ __('Notizen') }}</label>
                <textarea id="notes" name="notes" rows="3" class="textarea textarea-bordered w-full">{{ old('notes') }}</textarea>
            </div>
            <button class="btn btn-primary">{{ __('Anlegen') }}</button>
        </form>
    </div>
@endsection
