@extends('layouts.app')
@section('title', __('Schichtplan importieren') . ' — WorkDiary')
@section('nav-title', __('Schichtplan Import'))

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8">

    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('schedule.index') }}" class="btn btn-sm btn-ghost">← {{ __('Zurück') }}</a>
    </div>
    <x-page-title :title="__('Schichtplan importieren')" class="mb-6" />

    @if (session('error'))
        <div class="alert alert-error mb-4">{{ session('error') }}</div>
    @endif

    <div class="card bg-base-100 shadow-sm border border-base-300">
        <div class="card-body">
            <h2 class="card-title text-base">{{ __('CSV oder Excel-Datei hochladen') }}</h2>
            <p class="text-sm text-base-content/60 mb-4">
                {{ __('Unterstützte Formate: .csv (Semikolon-getrennt), .xlsx, .xls') }}<br>
                {{ __('Die erste Zeile muss Spaltenköpfe enthalten.') }}
            </p>

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
                    <button type="submit" class="btn btn-primary">{{ __('Weiter →') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="mt-6 rounded-box border border-base-300 bg-base-200/40 p-4 text-sm">
        <h3 class="mb-2 font-semibold">{{ __('Hinweise zum Format') }}</h3>
        <ul class="list-disc space-y-1 pl-5 text-base-content/70">
            <li>{{ __('Pflichtfelder: Datum, Mitarbeiter (Name oder E-Mail)') }}</li>
            <li>{{ __('Optionale Felder: Schichttyp, Von, Bis, Notiz, Status') }}</li>
            <li>{{ __('Datum-Format: YYYY-MM-DD oder DD.MM.YYYY') }}</li>
            <li>{{ __('Zeiten im Format HH:MM (z.B. 06:00)') }}</li>
            <li>{{ __('Im nächsten Schritt können die Spalten gemappt werden.') }}</li>
        </ul>
    </div>

</div>
@endsection
