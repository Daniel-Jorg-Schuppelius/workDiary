@extends('layouts.app')

@section('title', __('CSV-Imports'))
@section('nav-title', __('CSV-Imports'))

@section('content')
<x-index-page :subtitle="__('CSV-Imports für :org verwalten — Vorprüfung, Bestätigung & Verlauf.', ['org' => $organization->name])">
    <x-slot:actions>
        <x-icon-btn icon="add" tone="primary" size="sm"
                    :href="route('admin.imports.create')"
                    show-label>{{ __('Import starten') }}</x-icon-btn>
    </x-slot:actions>

    <x-filter-bar :action="route('admin.imports.index')" :reset="route('admin.imports.index')">
        <select name="entity" class="select select-sm select-bordered w-40 shrink-0" aria-label="{{ __('Entität') }}">
            <option value="">{{ __('Entität') }}</option>
            @foreach ($entities as $e)
                <option value="{{ $e->value }}" @selected(($filters['entity'] ?? '') === $e->value)>{{ $e->label() }}</option>
            @endforeach
        </select>
        <select name="state" class="select select-sm select-bordered w-40 shrink-0" aria-label="{{ __('Status') }}">
            <option value="">{{ __('Status') }}</option>
            @foreach ($states as $s)
                <option value="{{ $s->value }}" @selected(($filters['state'] ?? '') === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    @if ($runs->isEmpty())
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">cloud_upload</span>' />
    @else
        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('ID') }}</th>
                    <th>{{ __('Entität') }}</th>
                    <th>{{ __('Datei') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Zeilen') }}</th>
                    <th class="text-right">{{ __('Neu/Aktualisiert/Übersprungen/Fehler') }}</th>
                    <th>{{ __('Erstellt') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @foreach ($runs as $run)
                <tr>
                    <td class="font-mono text-sm">#{{ $run->id }}</td>
                    <td>{{ $run->entity->label() }}</td>
                    <td class="font-mono text-xs">{{ $run->input_filename }}</td>
                    <td><span class="badge badge-sm">{{ $run->state->label() }}</span></td>
                    <td class="text-right tabular-nums">{{ $run->rows_total }}</td>
                    <td class="text-right tabular-nums">{{ $run->rows_created }} / {{ $run->rows_updated }} / {{ $run->rows_skipped }} / {{ $run->rows_failed }}</td>
                    <td class="text-sm">{{ optional($run->created_at)->format('Y-m-d H:i') }}</td>
                    <td>
                        <x-icon-btn icon="visibility" size="sm" :href="route('admin.imports.show', $run)" />
                    </td>
                </tr>
            @endforeach
        </x-table>
        <div class="mt-4">{{ $runs->links() }}</div>
    @endif
</x-index-page>
@endsection
