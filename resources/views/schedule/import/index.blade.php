@extends('layouts.app')
@section('title', __('Schichtplan importieren') . ' — WorkDiary')
@section('nav-title', __('Schichtplan Import'))

@section('content')
<x-page-shell gap="6">
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" size="sm" :href="route('schedule.index')" show-label>{{ __('Zurück') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if (session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    <x-card :title="__('CSV oder Excel-Datei hochladen')" :subtitle="__('Unterstützte Formate: .csv (Semikolon-getrennt), .xlsx, .xls') . ' · ' . __('Die erste Zeile muss Spaltenköpfe enthalten.')">
            <form method="POST" action="{{ route('schedule.import.preview') }}" enctype="multipart/form-data">
                @csrf

                <div class="form-control mb-4">
                    <label class="label"><span class="label-text">{{ __('Datei auswählen') }} *</span></label>
                    <input type="file" name="file" accept=".csv,.xlsx,.xls"
                           class="file-input file-input-bordered w-full @error('file') file-input-error @enderror"
                           required>
                    @error('file')
                        <label class="label"><span class="label-text-alt text-error">{{ $message }}</span></label>
                    @enderror
                </div>

                <div class="form-control mb-6">
                    <label class="label">
                        <span class="label-text">{{ __('Standard-Status für importierte Schichten') }}</span>
                    </label>
                    <select name="default_status" class="select select-bordered w-full">
                        @foreach (\App\Models\ScheduledShift::$statuses as $key => $label)
                            <option value="{{ $key }}" @selected($key === 'draft')>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="card-actions justify-end">
                    <x-icon-btn icon="arrow_forward" tone="primary" type="submit" show-label>{{ __('Weiter') }}</x-icon-btn>
                </div>
            </form>
    </x-card>

    <div class="rounded-box border border-base-300 bg-base-200/40 p-4 text-sm">
        <h3 class="mb-2 font-semibold">{{ __('Hinweise zum Format') }}</h3>
        <ul class="list-disc space-y-1 pl-5 text-base-content/70">
            <li>{{ __('Pflichtfelder: Datum, Mitarbeiter (Name oder E-Mail)') }}</li>
            <li>{{ __('Optionale Felder: Schichttyp, Von, Bis, Notiz, Status') }}</li>
            <li>{{ __('Datum-Format: YYYY-MM-DD oder DD.MM.YYYY') }}</li>
            <li>{{ __('Zeiten im Format HH:MM (z.B. 06:00)') }}</li>
            <li>{{ __('Im nächsten Schritt können die Spalten gemappt werden.') }}</li>
        </ul>
    </div>

</x-page-shell>
@endsection
