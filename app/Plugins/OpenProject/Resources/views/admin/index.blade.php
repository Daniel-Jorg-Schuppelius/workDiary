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
<x-page-shell>
    <div class="space-y-4">
        {{-- Sync-Aktionen --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('OpenProject synchronisieren') }}</h1>
                <div class="flex gap-2">
                    <a href="{{ route('admin.openproject.push') }}" class="btn btn-ghost btn-sm">{{ __('Zeiten zurückbuchen') }}</a>
                    <a href="{{ route('admin.openproject.mappings.index') }}" class="btn btn-ghost btn-sm">{{ __('Zuordnungen verwalten') }}</a>
                </div>
            </div>
            <p class="mb-4 text-sm text-muted">
                {{ __('Projekte und Work Packages werden mit workDiary abgeglichen, anschließend die Zeiteinträge importiert. Zuordenbare Einträge werden direkt im Projekt gebucht, der Rest landet in der zentralen Zuordnungs-Inbox.') }}
            </p>

            @if (session('status'))
                <div class="alert alert-success mb-3 text-sm">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-error mb-3 text-sm">{{ $errors->first() }}</div>
            @endif

            <div class="grid gap-3 md:grid-cols-2">
                <form method="POST" action="{{ route('admin.openproject.sync') }}"
                      class="flex items-center justify-between gap-2 rounded-box bg-base-200/50 p-3">
                    @csrf
                    <div>
                        <div class="text-sm font-semibold">{{ __('Struktur + Zeiten synchronisieren') }}</div>
                        <div class="text-xs text-muted">{{ __('Nutzt die hinterlegten Zugangsdaten und das Zeitfenster.') }}</div>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Jetzt synchronisieren') }}</button>
                </form>

                <form method="POST" action="{{ route('admin.openproject.sync-structure') }}"
                      class="flex items-center justify-between gap-2 rounded-box bg-base-200/50 p-3">
                    @csrf
                    <div>
                        <div class="text-sm font-semibold">{{ __('Nur Struktur abgleichen') }}</div>
                        <div class="text-xs text-muted">{{ __('Projekte, Work Packages und Benutzer neu zuordnen.') }}</div>
                    </div>
                    <button type="submit" class="btn btn-sm">{{ __('Struktur abgleichen') }}</button>
                </form>
            </div>
        </div>

        {{-- Unzugeordnete Zeiteinträge → zentrale Zuordnungs-Inbox (MVP-103) --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Unzugeordnete Zeiteinträge') }}</h2>
                    <p class="text-sm text-muted">
                        {{ __('Nicht automatisch zuordenbare OpenProject-Einträge werden jetzt in der zentralen Zuordnungs-Inbox bearbeitet (Gruppe → Projekt zuordnen und buchen).') }}
                    </p>
                </div>
                <a href="{{ route('admin.integration.inbox', ['plugin' => 'openproject']) }}" class="btn btn-sm btn-primary">
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
