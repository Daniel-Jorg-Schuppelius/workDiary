@extends('layouts.app')

@section('content')
<div class="container mx-auto p-6 max-w-2xl">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold">{{ __('Kunden importieren (CSV)') }}</h1>
        <a href="{{ route('customers.index') }}" class="btn btn-ghost btn-sm">{{ __('Zurück') }}</a>
    </div>

    @if (session('error'))
        <div class="alert alert-error mb-4"><span>{{ session('error') }}</span></div>
    @endif

    <div class="card bg-base-100 shadow">
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
                    <button type="submit" class="btn btn-primary">{{ __('Importieren') }}</button>
                    <a href="{{ route('customers.export') }}" class="btn btn-ghost btn-sm">{{ __('Vorlage herunterladen') }}</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
