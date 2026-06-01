@extends('layouts.app')

@section('title', __('Datentransfer-Verlauf'))
@section('nav-title', __('Datentransfer'))

@section('content')
<x-index-page :subtitle="__('Import- und Export-Läufe von :org im Überblick.', ['org' => $organization->name])">
    <x-slot:actions>
        <x-icon-btn icon="upload_file" tone="primary" size="sm"
                    :href="route('admin.imports.create')"
                    show-label>{{ __('Import starten') }}</x-icon-btn>
    </x-slot:actions>

    @include('admin.data._tabs')

    <h3 class="mt-4 mb-2 text-sm font-semibold text-base-content/70">{{ __('Importe') }}</h3>
    @if ($imports->isEmpty())
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">upload_file</span>'
            :title="__('Noch keine Importe vorhanden')" />
    @else
        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('ID') }}</th>
                    <th>{{ __('Entität') }}</th>
                    <th>{{ __('Datei') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Neu/Aktualisiert/Übersprungen/Fehler') }}</th>
                    <th>{{ __('Erstellt') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @foreach ($imports as $run)
                <tr>
                    <td class="font-mono text-sm">#{{ $run->id }}</td>
                    <td>{{ $run->entity->label() }}</td>
                    <td class="font-mono text-xs">{{ $run->input_filename }}</td>
                    <td><span class="badge badge-sm">{{ $run->state->label() }}</span></td>
                    <td class="text-right tabular-nums">{{ $run->rows_created }} / {{ $run->rows_updated }} / {{ $run->rows_skipped }} / {{ $run->rows_failed }}</td>
                    <td class="text-sm">{{ optional($run->created_at)->format('Y-m-d H:i') }}</td>
                    <td><x-icon-btn icon="visibility" size="sm" :href="route('admin.imports.show', $run)" /></td>
                </tr>
            @endforeach
        </x-table>
    @endif

    <h3 class="mt-6 mb-2 text-sm font-semibold text-base-content/70">{{ __('Exporte') }}</h3>
    @if ($exports->isEmpty())
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">download</span>'
            :title="__('Noch keine Exporte vorhanden')" />
    @else
        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('ID') }}</th>
                    <th>{{ __('Entität') }}</th>
                    <th>{{ __('Format') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Zeilen') }}</th>
                    <th>{{ __('Erstellt') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @foreach ($exports as $run)
                <tr>
                    <td class="font-mono text-sm">#{{ $run->id }}</td>
                    <td>{{ $run->entity->label() }}</td>
                    <td><span class="badge badge-sm badge-ghost uppercase">{{ $run->format->value }}</span></td>
                    <td><span class="badge badge-sm">{{ $run->state->label() }}</span></td>
                    <td class="text-right tabular-nums">{{ $run->rows_total }}</td>
                    <td class="text-sm">{{ optional($run->created_at)->format('Y-m-d H:i') }}</td>
                    <td>
                        @if ($run->state->canDownload())
                            <x-icon-btn icon="download" size="sm" :href="route('admin.data.download', $run)" />
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-table>
    @endif
</x-index-page>
@endsection
