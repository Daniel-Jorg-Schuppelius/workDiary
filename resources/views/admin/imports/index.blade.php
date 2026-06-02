{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('CSV-Imports'))
@section('nav-title', __('CSV-Imports'))
@section('wrapper-height-class', 'min-h-[calc(100dvh_-_var(--app-header-h))] lg:h-[calc(100dvh_-_var(--app-header-h))] lg:overflow-clip')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('CSV-Imports für :org verwalten — Vorprüfung, Bestätigung & Verlauf.', ['org' => $organization->name])">
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
        <x-table scroll="flex" :pinRows="true" table-sort="server"
                 :route="route('admin.imports.index')" :current-sort="$sort" :current-dir="$dir"
                 :sort-params="array_filter(['entity' => $filters['entity'] ?: null, 'state' => $filters['state'] ?: null])">
            <x-slot:head>
                <tr>
                    <x-table.th sort="id">{{ __('ID') }}</x-table.th>
                    <x-table.th sort="entity">{{ __('Entität') }}</x-table.th>
                    <x-table.th sort="input_filename">{{ __('Datei') }}</x-table.th>
                    <x-table.th sort="state">{{ __('Status') }}</x-table.th>
                    <x-table.th sort="rows_total" align="right">{{ __('Zeilen') }}</x-table.th>
                    <th class="text-right">{{ __('Neu/Aktualisiert/Übersprungen/Fehler') }}</th>
                    <x-table.th sort="created_at">{{ __('Erstellt') }}</x-table.th>
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
        <x-pagination :paginator="$runs" />
    @endif
</x-index-page>
@endsection
