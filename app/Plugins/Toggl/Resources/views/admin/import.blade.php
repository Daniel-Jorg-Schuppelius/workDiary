@extends('layouts.app')
@section('title', __('Toggl-Import'))
@section('nav-title', __('Toggl-Import'))

@php
    $customerName = $customers->mapWithKeys(fn($c) => [$c->id => ($c->company ?: $c->name)]);
@endphp

@section('content')
<x-page-shell>
    <div class="space-y-4">
        {{-- Importquellen --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <h1 class="mb-1 font-['Space_Grotesk'] text-lg font-semibold">{{ __('Toggl Track importieren') }}</h1>
            <p class="mb-4 text-sm text-base-content/60">
                {{ __('Zeiteinträge per API abrufen oder einen Detailed-Report-CSV-Export hochladen. Zuordenbare Einträge werden direkt im Kundenprojekt gebucht, der Rest landet unten in der Inbox.') }}
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

        {{-- Inbox: unzugeordnete Einträge --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="mb-3">
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Unzugeordnete Einträge') }}</h2>
                <p class="text-sm text-base-content/60">
                    {{ __('Diese Toggl-Client/Projekt-Kombinationen ließen sich keinem Kunden bzw. Projekt zuordnen. Weise jede Gruppe einem bestehenden Projekt zu — die gespeicherten Einträge werden dann sofort gebucht und künftige Importe matchen automatisch.') }}
                </p>
            </div>

            @if ($groups->isEmpty())
                <p class="rounded-box border border-base-300 p-6 text-center text-sm text-base-content/60">
                    {{ __('Keine offenen Einträge. Alles zugeordnet.') }}
                </p>
            @else
                <div class="space-y-3">
                    @foreach ($groups as $group)
                        <div class="rounded-box border border-base-300 p-3">
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <x-status-badge tone="neutral" size="md">{{ $group->client_name ?: __('(ohne Client)') }}</x-status-badge>
                                    <span class="ml-2 font-semibold">{{ $group->project_name ?: __('(ohne Projekt)') }}</span>
                                    <span class="ml-2 text-sm text-base-content/60">
                                        {{ trans_choice(':count Eintrag|:count Einträge', $group->count, ['count' => $group->count]) }},
                                        {{ $group->minutes }} {{ __('Min.') }} ·
                                        {{ \Illuminate\Support\Carbon::parse($group->first_seen)->isoFormat('L') }} – {{ \Illuminate\Support\Carbon::parse($group->last_seen)->isoFormat('L') }}
                                    </span>
                                </div>
                                <form method="POST" action="{{ route('admin.toggl.pending.dismiss') }}"
                                      data-confirm-dialog
                                      data-confirm-message="{{ __('Diese Einträge verwerfen? Sie werden nicht gebucht.') }}">
                                    @csrf
                                    <input type="hidden" name="client_name" value="{{ $group->client_name }}">
                                    <input type="hidden" name="project_name" value="{{ $group->project_name }}">
                                    <button type="submit" class="btn btn-ghost btn-xs text-error">{{ __('Verwerfen') }}</button>
                                </form>
                            </div>

                            <form method="POST" action="{{ route('admin.toggl.pending.assign') }}"
                                  class="flex items-end gap-2 rounded-box bg-base-200/50 p-2">
                                @csrf
                                <input type="hidden" name="client_name" value="{{ $group->client_name }}">
                                <input type="hidden" name="project_name" value="{{ $group->project_name }}">
                                <label class="form-control flex-1">
                                    <span class="label-text text-xs">{{ __('Bestehendem Projekt zuordnen') }}</span>
                                    <select name="project_id" required class="select select-sm select-bordered">
                                        <option value="">{{ __('— Projekt wählen —') }}</option>
                                        @foreach ($projects as $project)
                                            <option value="{{ $project->id }}">
                                                {{ $project->name }} ({{ $customerName[$project->customer_id] ?? '—' }})
                                            </option>
                                        @endforeach
                                    </select>
                                </label>
                                <button type="submit" class="btn btn-sm btn-primary">{{ __('Zuordnen & buchen') }}</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-page-shell>
@endsection
