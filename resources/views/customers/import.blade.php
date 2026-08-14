{{--
  Created on   : Fri May 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : import.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Kunden importieren (CSV)'))
@section('nav-title', __('Kunden importieren (CSV)'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" size="sm" :href="route('customers.index')" show-label>{{ __('Zurück') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if (session('error'))
        <div class="alert alert-error"><span>{{ session('error') }}</span></div>
    @endif

    <div class="card border border-base-300 bg-base-100 shadow-xs">
        <div class="card-body">
            <form method="POST" action="{{ route('customers.import') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <label class="label" for="file">
                        <span class="label-text">{{ __('CSV-Datei') }}</span>
                    </label>
                    <input id="file" type="file" name="file" accept=".csv,text/csv" required
                           class="file-input file-input-bordered w-full @error('file') file-input-error @enderror">
                    @error('file')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="text-sm text-base-content/70">
                    <p class="font-semibold">{{ __('Hinweise zum Format:') }}</p>
                    <ul class="list-disc ml-5 mt-2 space-y-1">
                        <li>{{ __('Trennzeichen: Semikolon (empfohlen), Komma oder Tab.') }}</li>
                        <li>{{ __('Kopfzeile erforderlich – passende Spalten: Nummer, Name, Firma, USt-IdNr., E-Mail, Telefon, Straße, PLZ, Ort, Land, Währung, Stundensatz, Abrechenbar.') }}</li>
                        <li>{{ __('Existierende Kunden werden über die Spalte "Nummer" aktualisiert.') }}</li>
                        <li>{{ __('Tipp: Der Export der Kundenliste liefert ein passendes Format als Vorlage.') }}</li>
                    </ul>
                </div>

                <div class="flex gap-2">
                    <x-icon-btn icon="upload" tone="primary" size="sm" type="submit" show-label>{{ __('Importieren') }}</x-icon-btn>
                    <x-icon-btn icon="download" size="sm" :href="route('customers.export')" show-label>{{ __('Vorlage herunterladen') }}</x-icon-btn>
                </div>
            </form>
        </div>
    </div>
</x-page-shell>
@endsection
