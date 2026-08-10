@extends('layouts.app')
@section('title', __('Toggl-Import'))
@section('nav-title', __('Toggl-Import'))

@section('content')
<x-page-shell>
    <div class="space-y-4">
        {{-- Importquellen --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('Toggl Track importieren') }}</h1>
                <div class="flex gap-2">
                    <a href="{{ route('admin.toggl.import-api') }}" class="btn btn-ghost btn-sm">{{ __('Workspaces aus API importieren') }}</a>
                    <a href="{{ route('admin.toggl.import-export') }}" class="btn btn-ghost btn-sm">{{ __('Workspace-Export importieren') }}</a>
                    <a href="{{ route('admin.toggl.mappings.index') }}" class="btn btn-ghost btn-sm">{{ __('Zuordnungen verwalten') }}</a>
                </div>
            </div>
            <p class="mb-4 text-sm text-base-content/60">
                {{ __('Zeiteinträge per API abrufen oder einen Detailed-Report-CSV-Export hochladen. Zuordenbare Einträge werden direkt im Kundenprojekt gebucht, der Rest landet in der zentralen Zuordnungs-Inbox.') }}
            </p>

            @if (session('status'))
                <div class="alert alert-success mb-3 text-sm">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-error mb-3 text-sm">{{ $errors->first() }}</div>
            @endif

            <div class="grid gap-3 md:grid-cols-2">
                <form method="POST" action="{{ route('admin.toggl.sync') }}"
                      class="flex items-center justify-between gap-2 rounded-box bg-base-200/50 p-3">
                    @csrf
                    <div>
                        <div class="text-sm font-semibold">{{ __('Per API synchronisieren') }}</div>
                        <div class="text-xs text-base-content/60">{{ __('Nutzt das hinterlegte API-Token und Zeitfenster.') }}</div>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Jetzt synchronisieren') }}</button>
                </form>

                <form method="POST" action="{{ route('admin.toggl.import-csv') }}" enctype="multipart/form-data"
                      class="rounded-box bg-base-200/50 p-3 space-y-2">
                    @csrf
                    <div class="text-sm font-semibold">{{ __('CSV-Export hochladen') }}</div>
                    <div class="flex items-end gap-2">
                        <input type="file" name="csv" accept=".csv,text/csv" required
                               class="file-input file-input-sm file-input-bordered flex-1">
                        <button type="submit" class="btn btn-sm">{{ __('Importieren') }}</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Spiegelung workDiary → Toggl (nur bei aktivierter Zeit-Übertragung) --}}
        @if (($apiConfigured ?? false) && ($exportEnabled ?? false))
            <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('Zeiten nach Toggl übertragen') }}</h2>
                <p class="mb-3 text-sm text-base-content/60">
                    {{ __('Überträgt in workDiary erfasste Zeiten gemappter Projekte nach Toggl (z. B. Fernwartungssitzungen). Angelegt wird für den Token-Inhaber; bereits übertragene oder aus Toggl importierte Einträge werden übersprungen, die Einträge bleiben lokal abrechenbar.') }}
                </p>
                <form method="POST" action="{{ route('admin.toggl.export-api') }}" class="flex flex-wrap items-end gap-2">
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
                                data-confirm-message="{{ __('Übertragung jetzt ausführen? Es werden Zeiteinträge in Toggl angelegt.') }}">{{ __('Nach Toggl übertragen') }}</x-icon-btn>
                </form>
            </div>
        @endif

        {{-- Benutzerzuordnung (MVP-509): Modus sichtbar machen --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Benutzerzuordnung') }}</h2>
                    @if ($singleUserMode ?? false)
                        <p class="text-sm text-warning">
                            {{ __('Einbenutzer-Modus aktiv: Einträge ohne zuordenbaren Toggl-Benutzer werden auf :name gebucht.', ['name' => $defaultUserName ?? '—']) }}
                        </p>
                    @else
                        <p class="text-sm text-base-content/60">
                            {{ __('Mehrbenutzer-Modus: Jeder Toggl-Eintrag wird über die Benutzer-E-Mail dem passenden Benutzer zugeordnet. Unbekannte Benutzer landen sichtbar in der Zuordnungs-Inbox — nie still beim Hauptbenutzer.') }}
                        </p>
                    @endif
                </div>
                <a href="{{ route('admin.toggl.mappings.index') }}" class="btn btn-sm btn-ghost">{{ __('Zuordnungen verwalten') }}</a>
            </div>
        </div>

        {{-- Unzugeordnete Einträge → zentrale Zuordnungs-Inbox (MVP-103) --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Unzugeordnete Einträge') }}</h2>
                    <p class="text-sm text-base-content/60">
                        {{ __('Nicht automatisch zuordenbare Toggl-Einträge werden jetzt in der zentralen Zuordnungs-Inbox bearbeitet (Gruppe → Kunde + Projekt zuordnen und buchen).') }}
                    </p>
                </div>
                <a href="{{ route('admin.integration.inbox', ['plugin' => 'toggl']) }}" class="btn btn-sm btn-primary">
                    {{ __('Zur Zuordnungs-Inbox') }}
                    @if (($inboxOpenCount ?? 0) > 0)
                        <span class="badge badge-sm badge-warning ml-1">{{ $inboxOpenCount }}</span>
                    @endif
                </a>
            </div>
        </div>
    </div>
</x-page-shell>
@endsection
