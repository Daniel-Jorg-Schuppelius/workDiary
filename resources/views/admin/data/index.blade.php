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
        <div class="alert alert-error mt-4">
            <span class="material-symbols-outlined" aria-hidden="true">error</span>
            <span>{{ $message }}</span>
        </div>
    @enderror

    <form method="POST" action="{{ route('admin.data.export') }}" class="card bg-base-100 shadow-sm mt-4">
        @csrf
        <div class="card-body space-y-4">
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

                <x-input-field name="status" :label="__('Status')" class="input-sm"
                               placeholder="{{ __('z. B. active, archived') }}" />

                <x-input-field name="q" :label="__('Suche')" class="input-sm"
                               placeholder="{{ __('Name, Nummer …') }}" />

                <x-input-field type="date" name="from" :label="__('Von (Datum)')" class="input-sm" />

                <x-input-field type="date" name="to" :label="__('Bis (Datum)')" class="input-sm" />
            </div>

            <div class="text-sm text-base-content/70">
                {{ __('Nicht zutreffende Filter werden je Entität ignoriert. Zeitraum-Filter wirken auf Schichtpläne und Touren.') }}
            </div>

            <div class="card-actions justify-end">
                <x-button type="submit" tone="primary" size="sm" icon="download">{{ __('Export erstellen') }}</x-button>
            </div>
        </div>
    </form>

    <h3 class="mt-6 mb-2 text-sm font-semibold text-base-content/70">{{ __('Letzte Exporte') }}</h3>

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
                <tr>
                    <td>{{ $run->entity->label() }}</td>
                    <td><span class="badge badge-sm badge-ghost uppercase">{{ $run->format->value }}</span></td>
                    <td><span class="badge badge-sm">{{ $run->state->label() }}</span></td>
                    <td class="text-right tabular-nums">{{ $run->rows_total }}</td>
                    <td class="text-sm">{{ optional($run->created_at)->format('Y-m-d H:i') }}</td>
                    <td class="flex justify-end gap-1">
                        @if ($run->state->canDownload())
                            <x-icon-btn icon="download" size="sm" :href="route('admin.data.download', $run)" />
                        @endif
                        <x-action-form :action="route('admin.data.destroy', $run)" method="DELETE"
                              confirm-icon="delete"
                              confirm-tone="error"
                              :confirm="__('Export wirklich löschen?')">
                            <x-icon-btn icon="delete" size="sm" tone="ghost" type="submit" />
                        </x-action-form>
                    </td>
                </tr>
            @endforeach
        </x-table>
    @endif
</x-index-page>
@endsection
