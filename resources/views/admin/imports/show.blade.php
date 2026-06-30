{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Import #:id', ['id' => $run->id]))
@section('nav-title', __('Import #:id', ['id' => $run->id]))

@section('content')
<x-index-page :subtitle="__(':entity — :file', ['entity' => $run->entity->label(), 'file' => $run->input_filename])">
    <x-slot:actions>
        <x-icon-btn icon="arrow_back" size="sm" :href="route('admin.imports.index')" show-label>
            {{ __('Zurück') }}
        </x-icon-btn>

        @if ($run->state === \App\Enums\Import\ImportRunState::AwaitingApproval)
            <x-action-form :action="route('admin.imports.confirm', $run)">
                <x-button type="submit" tone="primary" size="sm" icon="play_arrow">{{ __('Import bestätigen & starten') }}</x-button>
            </x-action-form>
        @endif

        @if (in_array($run->state, [\App\Enums\Import\ImportRunState::AwaitingApproval, \App\Enums\Import\ImportRunState::Failed], true))
            <x-action-form :action="route('admin.imports.destroy', $run)" method="DELETE"
                  :confirm="__('Import wirklich verwerfen?')"
                  confirm-icon="delete"
                  confirm-tone="error"
                  :confirm-label="__('Verwerfen')">
                <x-button type="submit" tone="error" size="sm" class="btn-outline" icon="delete">{{ __('Verwerfen') }}</x-button>
            </x-action-form>
        @endif

        @if ($errors->total() > 0)
            <x-icon-btn icon="download" size="sm" :href="route('admin.imports.errors', $run)" show-label>
                {{ __('Fehler-CSV') }}
            </x-icon-btn>
        @endif
    </x-slot:actions>

    {{-- Status-Kacheln --}}
    <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
        <div class="card bg-base-100 shadow-sm"><div class="card-body p-3">
            <div class="text-xs text-base-content/60">{{ __('Status') }}</div>
            <div class="font-semibold">{{ $run->state->label() }}</div>
        </div></div>
        <div class="card bg-base-100 shadow-sm"><div class="card-body p-3">
            <div class="text-xs text-base-content/60">{{ __('Zeilen') }}</div>
            <div class="font-semibold tabular-nums">{{ $run->rows_total }}</div>
        </div></div>
        <div class="card bg-base-100 shadow-sm"><div class="card-body p-3">
            <div class="text-xs text-base-content/60">{{ __('Neu') }}</div>
            <div class="font-semibold tabular-nums text-success">{{ $run->rows_created }}</div>
        </div></div>
        <div class="card bg-base-100 shadow-sm"><div class="card-body p-3">
            <div class="text-xs text-base-content/60">{{ __('Aktualisiert') }}</div>
            <div class="font-semibold tabular-nums">{{ $run->rows_updated }}</div>
        </div></div>
        <div class="card bg-base-100 shadow-sm"><div class="card-body p-3">
            <div class="text-xs text-base-content/60">{{ __('Übersprungen') }}</div>
            <div class="font-semibold tabular-nums">{{ $run->rows_skipped }}</div>
        </div></div>
        <div class="card bg-base-100 shadow-sm"><div class="card-body p-3">
            <div class="text-xs text-base-content/60">{{ __('Fehler') }}</div>
            <div class="font-semibold tabular-nums {{ $run->rows_failed > 0 ? 'text-error' : '' }}">{{ $run->rows_failed }}</div>
        </div></div>
    </div>

    {{-- Hinweis: Fernwartungs-Sitzungen ohne Geräte-Zuordnung landen in der Inbox --}}
    @if ($run->entity === \App\Enums\Import\ImportEntity::RemoteSessions
        && $run->rows_skipped > 0
        && \Illuminate\Support\Facades\Route::has('admin.remote-support.pending.index'))
        <div class="alert alert-info">
            <span class="material-symbols-outlined" aria-hidden="true">inbox</span>
            <span>
                {{ __(':n Sitzungen konnten keinem Gerät zugeordnet werden und liegen in der Fernwartungs-Inbox. Ordne die Geräte-IDs einem Asset zu, um sie als Zeiteinträge zu buchen.', ['n' => $run->rows_skipped]) }}
            </span>
            <x-button :href="route('admin.remote-support.pending.index')" tone="primary" size="sm" icon="arrow_forward">{{ __('Zur Inbox') }}</x-button>
        </div>
    @endif

    {{-- Vorschau --}}
    @if (! empty($run->preview))
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <h3 class="font-semibold">{{ __('Vorschau (erste :n Zeilen)', ['n' => count($run->preview)]) }}</h3>
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>#</th>
                                @foreach (($run->preview[0]['data'] ?? []) as $col => $_)
                                    <th>{{ $col }}</th>
                                @endforeach
                                <th>{{ __('Hinweise') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($run->preview as $entry)
                                <tr class="{{ ! empty($entry['issues']) ? 'bg-error/10' : '' }}">
                                    <td class="font-mono text-xs">{{ $entry['row'] }}</td>
                                    @foreach ($entry['data'] as $col => $val)
                                        <td class="text-xs">{{ \Illuminate\Support\Str::limit((string) $val, 40) }}</td>
                                    @endforeach
                                    <td class="text-xs">
                                        @foreach ($entry['issues'] ?? [] as $iss)
                                            <div><x-status-badge tone="error" size="xs">{{ $iss['code'] }}</x-status-badge> {{ $iss['field'] }}: {{ $iss['message'] }}</div>
                                        @endforeach
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- Fehlerliste --}}
    @if ($errors->total() > 0)
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <h3 class="font-semibold">{{ __('Fehler (:n)', ['n' => $errors->total()]) }}</h3>
                <x-table table-sort="client">
                    <x-slot:head>
                        <tr>
                            <x-table.th sort type="number">{{ __('Zeile') }}</x-table.th>
                            <x-table.th sort type="string">{{ __('Feld') }}</x-table.th>
                            <x-table.th sort type="string">{{ __('Code') }}</x-table.th>
                            <x-table.th sort type="string">{{ __('Meldung') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @foreach ($errors as $err)
                        <tr>
                            <td class="font-mono text-xs">{{ $err->row_number }}</td>
                            <td class="font-mono text-xs">{{ $err->field }}</td>
                            <td><x-status-badge tone="error" size="xs">{{ $err->code->value }}</x-status-badge></td>
                            <td class="text-sm">{{ $err->message }}</td>
                        </tr>
                    @endforeach
                </x-table>
                <x-pagination :paginator="$errors" standing />
            </div>
        </div>
    @endif
</x-index-page>
@endsection
