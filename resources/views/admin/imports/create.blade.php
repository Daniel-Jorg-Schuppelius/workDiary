@extends('layouts.app')

@section('title', __('Import starten'))
@section('nav-title', __('Import starten'))

@section('content')
<x-index-page :subtitle="__('CSV-Datei für :org hochladen — Header werden geprüft und Daten als Vorschau angezeigt.', ['org' => $organization->name])">
    <x-slot:actions>
        <x-icon-btn icon="arrow_back" size="sm" :href="route('admin.imports.index')" show-label>
            {{ __('Zurück') }}
        </x-icon-btn>
    </x-slot:actions>

    <form method="POST" action="{{ route('admin.imports.preflight') }}" enctype="multipart/form-data" class="card bg-base-100 shadow-sm">
        @csrf
        <div class="card-body space-y-4">
            <x-select-field name="entity" :label="__('Entität')" class="select-sm w-64">
                @foreach ($entities as $e)
                    <option value="{{ $e->value }}" @selected($entity->value === $e->value)>{{ $e->label() }}</option>
                @endforeach
            </x-select-field>

            <label class="form-control">
                <span class="label-text">{{ __('CSV-Datei (max. :mb MB, :rows Zeilen)', ['mb' => 5, 'rows' => number_format(50000, 0, ',', '.')]) }}</span>
                <input type="file" name="file" required accept=".csv,.txt"
                       class="file-input file-input-sm file-input-bordered w-full max-w-md" />
            </label>

            @error('file')<div class="text-error text-sm">{{ $message }}</div>@enderror
            @error('entity')<div class="text-error text-sm">{{ $message }}</div>@enderror

            <div class="text-sm text-base-content/70">
                {{ __('Trennzeichen wird automatisch erkannt (Semikolon, Komma, Tab). Spaltenüberschriften können deutsch oder englisch sein.') }}
            </div>

            <div class="card-actions justify-end">
                <button type="submit" class="btn btn-primary btn-sm">
                    <span class="material-symbols-outlined" aria-hidden="true">upload</span>
                    {{ __('Vorprüfung starten') }}
                </button>
            </div>
        </div>
    </form>
</x-index-page>
@endsection
