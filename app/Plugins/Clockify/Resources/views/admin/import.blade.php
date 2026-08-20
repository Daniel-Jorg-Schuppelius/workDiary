{{--
  Created on   : Tue Jul 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : import.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Clockify-Import (MVP-134): Detailed-Report-CSV hochladen oder per API importieren.
--}}

@extends('layouts.app')
@section('title', __('Clockify-Import'))
@section('nav-title', __('Clockify-Import'))

@section('content')
<x-page-shell>
    <x-page-toolbar>
        <x-slot:title>{{ __('Clockify-Import') }}</x-slot:title>
        <x-slot:subtitle>{{ __('Zeiteinträge aus einem Clockify-Detailed-Report übernehmen.') }}</x-slot:subtitle>
    </x-page-toolbar>

    @if (session('status'))
        <div class="alert alert-success text-sm">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-error text-sm">{{ $errors->first() }}</div>
    @endif

    <x-card>
        <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('CSV hochladen') }}</h2>
        <p class="mb-3 text-sm text-base-content/60">
            {{ __('Clockify → Reports → Detailed → Export → CSV. Clients/Projekte werden über Namen bzw. gemerkte Zuordnungen gematcht; nicht Zuordenbares landet in der Zuordnungs-Inbox. Für Free-Konten (30 API-Requests/h) ist CSV der empfohlene Weg.') }}
        </p>
        <form method="POST" action="{{ route('admin.clockify.import-csv') }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-2">
            @csrf
            <input type="file" name="csv" accept=".csv,.txt" class="file-input file-input-bordered file-input-sm" required>
            <x-icon-btn icon="upload" tone="primary" size="sm" type="submit" show-label>{{ __('Importieren') }}</x-icon-btn>
        </form>
    </x-card>

    <x-card>
        <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('Direkt aus der Clockify-API importieren') }}</h2>
        @if ($apiConfigured)
            <p class="mb-3 text-sm text-base-content/60">
                {{ __('Holt Zeiteinträge aller Benutzer über die Reports-API (X-Api-Key). Ohne Zeitraum werden die letzten :days Tage abgefragt; bereits importierte Einträge werden übersprungen.', ['days' => $syncWindowDays]) }}
            </p>
            <form method="POST" action="{{ route('admin.clockify.import-api') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <label class="form-control">
                    <span class="label-text text-xs">{{ __('Von') }}</span>
                    <input type="date" name="from" value="{{ old('from') }}" class="input input-sm input-bordered">
                </label>
                <label class="form-control">
                    <span class="label-text text-xs">{{ __('Bis') }}</span>
                    <input type="date" name="to" value="{{ old('to') }}" class="input input-sm input-bordered">
                </label>
                <x-icon-btn icon="cloud_download" tone="primary" size="sm" type="submit" show-label>{{ __('Von API importieren') }}</x-icon-btn>
            </form>
        @else
            <div class="alert alert-warning text-sm">{{ __('Kein API-Key hinterlegt. API-Key (und optional Workspace-ID) in den Plugin-Einstellungen konfigurieren.') }}</div>
        @endif
    </x-card>

    @if ($apiConfigured && ($exportEnabled ?? false))
        <x-card>
            <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('Zeiten nach Clockify übertragen') }}</h2>
            <p class="mb-3 text-sm text-base-content/60">
                {{ __('Überträgt in workDiary erfasste Zeiten gemappter Projekte nach Clockify (z. B. Fernwartungssitzungen). Angelegt wird für den Inhaber des API-Keys; bereits übertragene oder aus Clockify importierte Einträge werden übersprungen, die Einträge bleiben lokal abrechenbar.') }}
            </p>
            <form method="POST" action="{{ route('admin.clockify.export-api') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <label class="form-control">
                    <span class="label-text text-xs">{{ __('Von') }}</span>
                    <input type="date" name="from" value="{{ old('from') }}" class="input input-sm input-bordered">
                </label>
                <label class="form-control">
                    <span class="label-text text-xs">{{ __('Bis') }}</span>
                    <input type="date" name="to" value="{{ old('to') }}" class="input input-sm input-bordered">
                </label>
                <x-icon-btn icon="cloud_upload" tone="primary" size="sm" type="submit" show-label
                            data-confirm-dialog
                            data-confirm-message="{{ __('Übertragung jetzt ausführen? Es werden Zeiteinträge in Clockify angelegt.') }}">{{ __('Nach Clockify übertragen') }}</x-icon-btn>
            </form>
        </x-card>
    @endif

    <x-card>
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Zuordnungs-Inbox') }}</h2>
                <p class="text-sm text-base-content/60">{{ __('Offene, noch nicht zugeordnete Import-Gruppen: :n', ['n' => $inboxOpenCount]) }}</p>
            </div>
            <x-icon-btn icon="inbox" tone="outline" size="sm" :href="route('admin.integration.inbox')" show-label>{{ __('Zur Inbox') }}</x-icon-btn>
        </div>
    </x-card>
</x-page-shell>
@endsection
