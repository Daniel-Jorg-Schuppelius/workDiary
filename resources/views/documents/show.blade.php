{{--
  Created on   : Tue Jul 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Dokument-Detailseite (Rang 28): Read-only-Trägerseite — Stammdaten,
  Versionshistorie und das Externe-Beteiligte-Panel (Feature 033).
--}}

@extends('layouts.app')
@section('title', $document->title)
@section('nav-title', __('Dokument'))

@section('content')
@php
    /** @var \App\Models\Document $document */
    $documentable = $document->documentable;
    $refLabel = $documentable?->title ?? $documentable?->name;
@endphp
<x-page-shell>
    <x-page-toolbar>
        <x-slot:title>{{ $document->title }}</x-slot:title>
        <x-slot:subtitle>
            {{ $document->document_type->label() }}@if ($refLabel !== null) · {{ \App\Support\EntityType::label($document->documentable_type) }}: {{ $refLabel }}@endif
        </x-slot:subtitle>
        <x-slot:actions>
            <x-status-badge size="sm" :tone="$document->effectiveStatus()->tone()">{{ $document->effectiveStatus()->label() }}</x-status-badge>
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('documents.index')" show-label>{{ __('Zur Übersicht') }}</x-icon-btn>
            @if ($document->currentVersion)
                <x-icon-btn icon="download" tone="outline" size="sm" :href="route('documents.download', $document)" show-label>{{ __('Herunterladen') }}</x-icon-btn>
            @endif
        </x-slot:actions>
    </x-page-toolbar>

    @if (session('status'))
        <div class="alert alert-success text-sm">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-error text-sm">{{ session('error') }}</div>
    @endif

    <x-card :title="__('Stammdaten')" icon="badge">
        <x-detail-grid>
            <x-detail-grid.row :label="__('Typ')" :value="$document->document_type->label()" />
            <x-detail-grid.row :label="__('Status')" :value="$document->effectiveStatus()->label()" />
            <x-detail-grid.row :label="__('Gültig von')" :value="$document->valid_from?->fdate()" />
            <x-detail-grid.row :label="__('Gültig bis')" :value="$document->valid_until?->fdate()" />
            <x-detail-grid.row :label="__('Erstellt von')" :value="$document->creator?->name" />
            @if ($document->description)
                <x-detail-grid.row :label="__('Beschreibung')" :value="$document->description" />
            @endif
        </x-detail-grid>
    </x-card>

    <x-card :title="__('Versionen')" icon="history" :count="$document->versions->count()">
        @if ($document->versions->isEmpty())
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">history</span>' :title="__('Noch keine Version hochgeladen.')" compact />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Version') }}</th>
                        <th>{{ __('Datei') }}</th>
                        <th>{{ __('Hochgeladen von') }}</th>
                        <th>{{ __('Datum') }}</th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @foreach ($document->versions as $version)
                    <tr>
                        <td class="tabular-nums">
                            v{{ $version->version_no }}
                            @if ((int) $document->current_version_id === (int) $version->id)
                                <x-status-badge size="xs" tone="success">{{ __('aktuell') }}</x-status-badge>
                            @endif
                        </td>
                        <td class="max-w-xs truncate text-sm">{{ $version->original_name }}</td>
                        <td class="text-sm">{{ $version->uploader?->name ?? '—' }}</td>
                        <td class="text-sm tabular-nums">{{ $version->created_at?->fdatetime() }}</td>
                        <td class="text-right">
                            <x-icon-btn icon="download" tone="ghost" size="xs"
                                        :href="route('documents.download', [$document, $version])"
                                        :label="__('Herunterladen')" />
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>

    {{-- Externe Beteiligte (Feature 033, Rang 28): Einladen/Widerrufen je Dokument. --}}
    @include('external-participants._panel', ['subject' => $document, 'externalType' => 'document'])
</x-page-shell>
@endsection
