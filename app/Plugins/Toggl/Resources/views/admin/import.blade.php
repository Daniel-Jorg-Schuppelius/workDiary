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
