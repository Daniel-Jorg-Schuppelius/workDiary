{{--
  Created on   : Wed May 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : import.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Toggl-Import'))
@section('nav-title', __('Toggl-Import'))

@section('content')
<x-index-page :title="__('Toggl Track importieren')" :subtitle="__('Zeiteinträge per API abrufen oder einen Detailed-Report-CSV-Export hochladen. Zuordenbare Einträge werden direkt im Kundenprojekt gebucht, der Rest landet in der zentralen Zuordnungs-Inbox.')">
    <x-slot:actions>
        <x-button :href="route('admin.toggl.import-api')" tone="ghost" size="sm">{{ __('Workspaces aus API importieren') }}</x-button>
        <x-button :href="route('admin.toggl.import-export')" tone="ghost" size="sm">{{ __('Workspace-Export importieren') }}</x-button>
        <x-button :href="route('admin.toggl.mappings.index')" tone="ghost" size="sm">{{ __('Zuordnungen verwalten') }}</x-button>
    </x-slot:actions>

    {{-- Importquellen --}}
    <x-card>
        <x-validation-errors first class="mb-3" />

        <div class="grid gap-3 md:grid-cols-2">
            <form method="POST" action="{{ route('admin.toggl.sync') }}"
                  class="flex items-center justify-between gap-2 rounded-box bg-base-200/50 p-3">
                @csrf
                <div>
                    <div class="text-sm font-semibold">{{ __('Per API synchronisieren') }}</div>
                    <div class="text-xs text-muted">{{ __('Nutzt das hinterlegte API-Token und Zeitfenster.') }}</div>
                </div>
                <x-button type="submit">{{ __('Jetzt synchronisieren') }}</x-button>
            </form>

            <form method="POST" action="{{ route('admin.toggl.import-csv') }}" enctype="multipart/form-data"
                  class="rounded-box bg-base-200/50 p-3 space-y-2">
                @csrf
                <div class="text-sm font-semibold">{{ __('CSV-Export hochladen') }}</div>
                <div class="flex items-end gap-2">
                    <input type="file" name="csv" accept=".csv,text/csv" required
                           class="file-input file-input-sm file-input-bordered flex-1">
                    <x-button type="submit" tone="plain">{{ __('Importieren') }}</x-button>
                </div>
            </form>
        </div>
    </x-card>

    {{-- Spiegelung workDiary → Toggl (nur bei aktivierter Zeit-Übertragung) --}}
    @if (($apiConfigured ?? false) && ($exportEnabled ?? false))
        <x-card>
            <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('Zeiten nach Toggl übertragen') }}</h2>
            <p class="mb-3 text-sm text-muted">
                {{ __('Überträgt in workDiary erfasste Zeiten gemappter Projekte nach Toggl (z. B. Fernwartungssitzungen). Angelegt wird für den Token-Inhaber; bereits übertragene oder aus Toggl importierte Einträge werden übersprungen, die Einträge bleiben lokal abrechenbar.') }}
            </p>
            <form method="POST" action="{{ route('admin.toggl.export-api') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <x-date-range :from="old('from')" :to="old('to')" />
                <x-icon-btn icon="cloud_upload" tone="primary" size="sm" type="submit" show-label
                            data-confirm-dialog
                            data-confirm-message="{{ __('Übertragung jetzt ausführen? Es werden Zeiteinträge in Toggl angelegt.') }}">{{ __('Nach Toggl übertragen') }}</x-icon-btn>
            </form>
        </x-card>
    @endif

    {{-- Benutzerzuordnung (MVP-509): Modus sichtbar machen --}}
    <x-card>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Benutzerzuordnung') }}</h2>
                @if ($singleUserMode ?? false)
                    <p class="text-sm text-warning">
                        {{ __('Einbenutzer-Modus aktiv: Einträge ohne zuordenbaren Toggl-Benutzer werden auf :name gebucht.', ['name' => $defaultUserName ?? '—']) }}
                    </p>
                @else
                    <p class="text-sm text-muted">
                        {{ __('Mehrbenutzer-Modus: Jeder Toggl-Eintrag wird über die Benutzer-E-Mail dem passenden Benutzer zugeordnet. Unbekannte Benutzer landen sichtbar in der Zuordnungs-Inbox — nie still beim Hauptbenutzer.') }}
                    </p>
                @endif
            </div>
            <x-button :href="route('admin.toggl.mappings.index')" tone="ghost">{{ __('Zuordnungen verwalten') }}</x-button>
        </div>
    </x-card>

    {{-- Unzugeordnete Einträge → zentrale Zuordnungs-Inbox (MVP-103) --}}
    <x-card>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Unzugeordnete Einträge') }}</h2>
                <p class="text-sm text-muted">
                    {{ __('Nicht automatisch zuordenbare Toggl-Einträge werden jetzt in der zentralen Zuordnungs-Inbox bearbeitet (Gruppe → Kunde + Projekt zuordnen und buchen).') }}
                </p>
            </div>
            <x-button :href="route('admin.integration.inbox', ['plugin' => 'toggl'])">
                {{ __('Zur Zuordnungs-Inbox') }}
                @if (($inboxOpenCount ?? 0) > 0)
                    <x-status-badge tone="warning" class="ml-1">{{ $inboxOpenCount }}</x-status-badge>
                @endif
            </x-button>
        </div>
    </x-card>
</x-index-page>
@endsection
