@extends('layouts.app')

@section('title', __('Neue Rechnung'))
@section('nav-title', __('Neue Rechnung'))

@section('content')
<div class="max-w-2xl mx-auto bg-base-100 p-6 rounded-box shadow space-y-4">
    <h1 class="text-2xl font-bold">{{ __('Rechnung aus Zeiteinträgen erstellen') }}</h1>

    @if ($errors->any())
        <div class="alert alert-error">
            <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('invoices.store') }}" class="space-y-4">
        @csrf

        <label class="form-control">
            <span class="label-text">{{ __('Kunde') }}</span>
            <select name="customer_id" required class="select select-bordered">
                <option value="">{{ __('-- bitte wählen --') }}</option>
                @foreach ($customers as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>
        </label>

        <label class="form-control">
            <span class="label-text">{{ __('Projekt (optional)') }}</span>
            <select name="project_id" class="select select-bordered">
                <option value="">{{ __('alle Projekte des Kunden') }}</option>
                @foreach ($projects as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                @endforeach
            </select>
        </label>

        <div class="grid grid-cols-2 gap-4">
            <label class="form-control">
                <span class="label-text">{{ __('Von') }}</span>
                <input type="date" name="from" value="{{ old('from', $defaultFrom ?? '') }}" class="input input-bordered">
            </label>
            <label class="form-control">
                <span class="label-text">{{ __('Bis') }}</span>
                <input type="date" name="to" value="{{ old('to', $defaultTo ?? '') }}" class="input input-bordered">
            </label>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('invoices.index') }}" class="btn btn-ghost">{{ __('Abbrechen') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('Entwurf erstellen') }}</button>
        </div>
    </form>
</div>
@endsection
