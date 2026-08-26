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
    <x-slot:toolbar>
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
    </x-slot:toolbar>

    @if (session('status'))
        <div class="alert alert-success text-sm">{{ session('status') }}</div>
    @endif
    @php
        // Dokumenttyp/Fristen erkennen (Feature 148, MVP-732): OCR-/Textanalyse
        // über das php-pdf-toolkit; Chips werden einzeln übernommen.
        $aiView = app(\App\Services\Ai\Suggestions\SuggestionViewData::class);
        $aiDmsUsable = $aiView->capabilityUsable(\App\Services\Ai\Suggestions\DocumentMetadataSuggestionService::CAPABILITY)
            && \Illuminate\Support\Facades\Gate::allows('update', $document)
            && $document->currentVersion !== null;
        $aiDms = $aiDmsUsable
            ? $aiView->openSuggestionsFor($document->getMorphClass(), collect([$document]), \App\Services\Ai\Suggestions\DocumentMetadataSuggestionService::CAPABILITY)->get($document->id)
            : null;
    @endphp
    <x-card :title="__('Stammdaten')" icon="badge">
        @if ($aiDmsUsable && $aiDms === null)
            <x-slot:actions>
                <x-action-form :action="route('ai.assist.document', $document)">
                    <x-icon-btn icon="auto_awesome" tone="info" size="sm" type="submit" show-label
                                :title="__('ai.assist.analyze_document')">{{ __('ai.assist.analyze_document') }}</x-icon-btn>
                </x-action-form>
            </x-slot:actions>
        @endif
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
        @if ($aiDms !== null)
            @include('ai._field_chips', [
                'suggestion' => $aiDms,
                'chips' => \App\Services\Ai\Suggestions\DocumentMetadataSuggestionService::extractedValues($aiDms),
            ])
        @endif
    </x-card>

    <x-card :title="__('Versionen')" icon="history" :count="$document->versions->count()">
        @if ($document->versions->isEmpty())
            <x-empty-state icon="history" :title="__('Noch keine Version hochgeladen.')" compact />
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

    {{-- Kundenfreigabe (Welle D — Dokument-Spiegelung ins Kundenportal). --}}
    <x-card :title="__('document.customer.section')" icon="share">
        @php $isReleasable = $document->isReleasableToCustomer(); @endphp
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-col gap-1">
                @if ($document->customer_visible)
                    <x-status-badge size="sm" tone="success">{{ __('document.customer.released') }}</x-status-badge>
                    <span class="text-xs text-muted">
                        {{ __('document.customer.released_at') }}: {{ $document->customer_released_at?->fdatetime() ?? '—' }}
                        @if ($document->customerReleaser)
                            · {{ __('document.customer.released_by') }}: {{ $document->customerReleaser->name }}
                        @endif
                    </span>
                @else
                    <x-status-badge size="sm" tone="ghost" outline>{{ __('document.customer.not_released') }}</x-status-badge>
                    @unless ($isReleasable)
                        <span class="text-xs text-muted">{{ __('document.customer.not_linked_hint') }}</span>
                    @endunless
                @endif
            </div>
            @can('releaseToCustomer', $document)
                <div class="flex items-center gap-2">
                    @if ($document->customer_visible)
                        <x-action-form :action="route('documents.customer-revoke', $document)"
                              data-confirm-title="{{ __('document.customer.action.revoke') }}"
                              :confirm="__('document.customer.confirm_revoke')"
                              confirm-icon="link_off"
                              confirm-tone="warning"
                              :confirm-label="__('document.customer.action.revoke')">
                            <x-icon-btn icon="link_off" tone="warning" size="sm" type="submit" show-label>{{ __('document.customer.action.revoke') }}</x-icon-btn>
                        </x-action-form>
                    @elseif ($isReleasable)
                        <x-action-form :action="route('documents.customer-release', $document)">
                            <x-icon-btn icon="share" tone="primary" size="sm" type="submit" show-label>{{ __('document.customer.action.release') }}</x-icon-btn>
                        </x-action-form>
                    @endif
                </div>
            @endcan
        </div>
    </x-card>

    {{-- Externe Beteiligte (Feature 033, Rang 28): Einladen/Widerrufen je Dokument. --}}
    @include('external-participants._panel', ['subject' => $document, 'externalType' => 'document'])
</x-page-shell>
@endsection
