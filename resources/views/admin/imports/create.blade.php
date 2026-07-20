@extends('layouts.app')

@section('title', __('Import starten'))
@section('nav-title', __('Import starten'))

@section('content')
<x-index-page :subtitle="__('CSV- oder Excel-Datei für :org hochladen — Header werden geprüft und Daten als Vorschau angezeigt.', ['org' => $organization->name])">
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

            {{-- Mustervorlage je Entität (Feature 020 MVP; Vollaudit 2026-07, N8). --}}
            <a class="link link-hover inline-flex items-center gap-1 text-sm" href="{{ route('admin.imports.template', ['entity' => $entity->value]) }}">
                <span class="material-symbols-outlined text-base" aria-hidden="true">download</span>
                {{ __('import.template.download') }} ({{ $entity->label() }})
            </a>

            {{-- MVP-438: iCal-Beispieldatei für die Zeiterfassungs-Importe. --}}
            @if(in_array($entity, [\App\Enums\Import\ImportEntity::Attendances, \App\Enums\Import\ImportEntity::ProjectTimes], true))
                <a class="link link-hover inline-flex items-center gap-1 text-sm" href="{{ route('admin.imports.icalSample', ['entity' => $entity->value]) }}">
                    <span class="material-symbols-outlined text-base" aria-hidden="true">calendar_month</span>
                    {{ __('iCal-Beispieldatei herunterladen') }}
                </a>
            @endif

            <fieldset class="form-control max-w-xl">
                <legend class="label-text font-semibold">{{ __('Bei unzuordenbaren Zeilen') }}</legend>
                <label class="label cursor-pointer justify-start gap-2 py-1">
                    <input type="radio" name="match_policy" value="auto_create" class="radio radio-sm" checked>
                    <span class="label-text">{{ __('Direkt anlegen bzw. aktualisieren (Standard)') }}</span>
                </label>
                <label class="label cursor-pointer justify-start gap-2 py-1">
                    <input type="radio" name="match_policy" value="inbox_first" class="radio radio-sm">
                    <span class="label-text">{{ __('In die Zuordnungs-Inbox legen statt anlegen (nur Kunden/Lieferanten/Artikel)') }}</span>
                </label>
            </fieldset>

            <label class="form-control">
                <span class="label-text">{{ __('CSV-, Excel- oder iCal-Datei (.csv, .xlsx, .ics, max. :mb MB, :rows Zeilen)', ['mb' => 5, 'rows' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(50000, 0, withThousandsSeparator: true)]) }}</span>
                <input type="file" name="file" required accept=".csv,.txt,.xlsx,.ics"
                       class="file-input file-input-sm file-input-bordered w-full max-w-md" />
            </label>

            {{-- MVP-438: optionale iCal-Kategorie-Allowlist (nur Stempelungen) —
                 damit ein voller Kalender nicht pauschal als Anwesenheit gilt. --}}
            @if($entity === \App\Enums\Import\ImportEntity::Attendances)
                <label class="form-control max-w-md">
                    <span class="label-text">{{ __('iCal-Kategorie-Allowlist (optional, kommagetrennt)') }}</span>
                    <input type="text" name="ical_category_allowlist" maxlength="500"
                           placeholder="{{ __('z. B. Arbeitszeit, Einsatz') }}"
                           class="input input-sm input-bordered w-full" />
                    <span class="label-text-alt text-base-content/60">{{ __('Nur iCal-Events dieser Kategorien werden als Anwesenheit gewertet.') }}</span>
                </label>
            @endif

            @error('file')<div class="text-error text-sm">{{ $message }}</div>@enderror
            @error('entity')<div class="text-error text-sm">{{ $message }}</div>@enderror

            <div class="text-sm text-base-content/70">
                {{ __('Trennzeichen wird automatisch erkannt (Semikolon, Komma, Tab). Spaltenüberschriften können deutsch oder englisch sein.') }}
                {{ __('Bei Excel-Dateien wird das erste Tabellenblatt importiert; Datums- und Zahlenzellen werden automatisch umgewandelt.') }}
            </div>

            <div class="card-actions justify-end">
                <x-button type="submit" tone="primary" size="sm" icon="upload">{{ __('Vorprüfung starten') }}</x-button>
            </div>
        </div>
    </form>
</x-index-page>
@endsection
