{{--
  Created on   : Tue Jun 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('OpenProject'))
@section('nav-title', __('OpenProject'))

@section('content')
<x-index-page :title="__('OpenProject synchronisieren')" :subtitle="__('Projekte und Work Packages werden mit workDiary abgeglichen, anschließend die Zeiteinträge importiert. Zuordenbare Einträge werden direkt im Projekt gebucht, der Rest landet in der zentralen Zuordnungs-Inbox.')">
    <x-slot:actions>
        <x-button :href="route('admin.openproject.push')" tone="ghost" size="sm">{{ __('Zeiten zurückbuchen') }}</x-button>
        <x-button :href="route('admin.openproject.mappings.index')" tone="ghost" size="sm">{{ __('Zuordnungen verwalten') }}</x-button>
    </x-slot:actions>

    {{-- Sync-Aktionen --}}
    <x-card>
        <x-validation-errors first class="mb-3" />

        <div class="grid gap-3 md:grid-cols-2">
            <form method="POST" action="{{ route('admin.openproject.sync') }}"
                  class="flex items-center justify-between gap-2 rounded-box bg-base-200/50 p-3">
                @csrf
                <div>
                    <div class="text-sm font-semibold">{{ __('Struktur + Zeiten synchronisieren') }}</div>
                    <div class="text-xs text-muted">{{ __('Nutzt die hinterlegten Zugangsdaten und das Zeitfenster.') }}</div>
                </div>
                <x-button type="submit">{{ __('Jetzt synchronisieren') }}</x-button>
            </form>

            <form method="POST" action="{{ route('admin.openproject.sync-structure') }}"
                  class="flex items-center justify-between gap-2 rounded-box bg-base-200/50 p-3">
                @csrf
                <div>
                    <div class="text-sm font-semibold">{{ __('Nur Struktur abgleichen') }}</div>
                    <div class="text-xs text-muted">{{ __('Projekte, Work Packages und Benutzer neu zuordnen.') }}</div>
                </div>
                <x-button type="submit" tone="plain">{{ __('Struktur abgleichen') }}</x-button>
            </form>
        </div>
    </x-card>

    {{-- Unzugeordnete Zeiteinträge → zentrale Zuordnungs-Inbox (MVP-103) --}}
    <x-card>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Unzugeordnete Zeiteinträge') }}</h2>
                <p class="text-sm text-muted">
                    {{ __('Nicht automatisch zuordenbare OpenProject-Einträge werden jetzt in der zentralen Zuordnungs-Inbox bearbeitet (Gruppe → Projekt zuordnen und buchen).') }}
                </p>
            </div>
            <x-button :href="route('admin.integration.inbox', ['plugin' => 'openproject'])">
                {{ __('Zur Zuordnungs-Inbox') }}
                @if (($inboxOpenCount ?? 0) > 0)
                    <x-status-badge tone="warning" class="ml-1">{{ $inboxOpenCount }}</x-status-badge>
                @endif
            </x-button>
        </div>
    </x-card>
</x-index-page>
@endsection
