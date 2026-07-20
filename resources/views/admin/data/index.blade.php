@extends('layouts.app')

@section('title', __('Datentransfer'))
@section('nav-title', __('Datentransfer'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Daten von :org als CSV oder Excel exportieren — gleiche Spalten wie der Import (Round-Trip).', ['org' => $organization->name])">
    <x-slot:actions>
        <x-icon-btn icon="upload_file" tone="primary" size="sm"
                    :href="route('admin.imports.create')"
                    show-label>{{ __('Import starten') }}</x-icon-btn>
    </x-slot:actions>

    @include('admin.data._tabs')

    @error('export')
        <div class="alert alert-error mt-4" role="alert">
            <x-icon name="error" aria-hidden="true" />
            <span>{{ $message }}</span>
        </div>
    @enderror

    <form method="POST" action="{{ route('admin.data.export') }}" class="mt-4">
        @csrf
        <x-card :title="__('Export erstellen')" icon="download">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-select-field name="entity" :label="__('Entität')" class="select-sm">
                    @foreach ($entities as $e)
                        <option value="{{ $e->value }}" @selected($entity->value === $e->value)>{{ $e->label() }}</option>
                    @endforeach
                </x-select-field>

                <x-select-field name="format" :label="__('Format')" class="select-sm">
                    @foreach ($formats as $f)
                        <option value="{{ $f->value }}">{{ $f->label() }}</option>
                    @endforeach
                </x-select-field>

                {{-- Status ist entitätsabhängig (data-depends-on="entity"): je Entität
                     nur die tatsächlich gefilterten Werte; Users/Schichtpläne kennen
                     keinen Status-Filter → nur „Alle". --}}
                <x-select-field name="status" :label="__('Status')" class="select-sm" data-depends-on="entity">
                    <option value="">{{ __('Alle') }}</option>
                    <option value="active"   data-parent="customers">{{ __('Aktiv') }}</option>
                    <option value="archived" data-parent="customers">{{ __('Archiviert') }}</option>
                    <option value="active"   data-parent="projects">{{ __('Aktiv') }}</option>
                    <option value="archived" data-parent="projects">{{ __('Archiviert') }}</option>
                    <option value="active"   data-parent="materials">{{ __('Aktiv') }}</option>
                    <option value="inactive" data-parent="materials">{{ __('Inaktiv') }}</option>
                    @foreach (\App\Enums\Tour\TourStatus::cases() as $ts)
                        <option value="{{ $ts->value }}" data-parent="tours">{{ $ts->label() }}</option>
                    @endforeach
                </x-select-field>

                <x-input-field name="q" :label="__('Suche')" class="input-sm"
                               placeholder="{{ __('Name, Nummer …') }}" />

                <x-date-range class="sm:col-span-2" layout="split" form-control
                              from-name="from" to-name="to"
                              :from-label="__('Von (Datum)')" :to-label="__('Bis (Datum)')" />
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-base-300 pt-4">
                <p class="flex items-start gap-1.5 text-xs text-base-content/60">
                    <x-icon name="info" class="mt-px text-base-content/40" size="1em" />
                    <span>{{ __('Nicht zutreffende Filter werden je Entität ignoriert. Zeitraum-Filter wirken auf Schichtpläne und Touren.') }}</span>
                </p>
                <x-button type="submit" tone="primary" size="sm" icon="download">{{ __('Export erstellen') }}</x-button>
            </div>
        </x-card>
    </form>

    <h3 class="mb-2 mt-6 flex items-center gap-2 text-sm font-semibold text-base-content/70">
        <x-icon name="history" class="text-base-content/50" size="1em" />
        {{ __('Letzte Exporte') }}
        @if ($runs->isNotEmpty())
            <span class="font-normal text-base-content/40">({{ $runs->count() }})</span>
        @endif
    </h3>

    @if ($runs->isEmpty())
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">download</span>'
            :title="__('Noch keine Exporte vorhanden')"
            :message="__('Erstelle oben einen Export, um ihn hier wiederzufinden.')" />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('Entität') }}</th>
                    <th>{{ __('Format') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Zeilen') }}</th>
                    <th>{{ __('Erstellt') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @foreach ($runs as $run)
                <tr class="hover">
                    <td class="font-medium">{{ $run->entity->label() }}</td>
                    <td><x-status-badge tone="neutral" outline class="uppercase">{{ $run->format->label() }}</x-status-badge></td>
                    <td><x-status-badge :tone="$run->state->tone()">{{ $run->state->label() }}</x-status-badge></td>
                    <td class="text-right tabular-nums">{{ number_format((int) $run->rows_total, 0, ',', '.') }}</td>
                    <td class="text-sm text-base-content/70">{{ $run->created_at?->fdatetime() }}</td>
                    <td class="flex justify-end gap-1">
                        @if ($run->state->canDownload())
                            <x-icon-btn icon="download" size="sm" tone="primary"
                                        :label="__('Herunterladen')" :href="route('admin.data.download', $run)" />
                        @endif
                        <x-action-form :action="route('admin.data.destroy', $run)" method="DELETE"
                              confirm-icon="delete"
                              confirm-tone="error"
                              :confirm="__('Export wirklich löschen?')">
                            <x-icon-btn icon="delete" size="sm" tone="ghost" type="submit" :label="__('Löschen')" />
                        </x-action-form>
                    </td>
                </tr>
            @endforeach
        </x-table>
    @endif
</x-index-page>
@endsection
