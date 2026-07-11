{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Detailansicht einer Eingangs-E-Rechnung (Nachtrag 045b): Kernfelder +
     Positionen — bei jedem Aufruf frisch aus dem abgelegten Original geparst. --}}

@extends('layouts.app')

@section('title', $document->title)
@section('nav-title', __('Eingangs-E-Rechnung'))

@section('content')
<x-page-shell>
    <x-page-toolbar>
        <x-slot:title>{{ $document->title }}</x-slot:title>
        <x-slot:subtitle>{{ $document->description }}</x-slot:subtitle>
        <x-slot:actions>
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('finance.incoming-invoices.index')" show-label>{{ __('Zur Liste') }}</x-icon-btn>
            <x-icon-btn icon="download" tone="outline" size="sm"
                        :href="route('documents.download', $document)"
                        show-label>{{ __('Original (XML/PDF)') }}</x-icon-btn>
            <x-icon-btn icon="code" tone="outline" size="sm"
                        :href="route('finance.incoming-invoices.xml', $document)"
                        :title="__('Extrahierte Rechnungs-XML (bei ZUGFeRD aus dem PDF)')"
                        show-label>{{ __('Rechnungs-XML') }}</x-icon-btn>
        </x-slot:actions>
    </x-page-toolbar>

    @if ($parsed === null || $summary === null)
        <x-empty-state icon="report" tone="warning" framed
                       :title="__('Das Original konnte nicht (mehr) als E-Rechnung geparst werden.')"
                       :message="__('Die Datei bleibt als Dokument verfügbar (Original herunterladen).')" />
    @else
        <x-card :title="__('Rechnungsdaten')">
            <x-detail-grid>
                <x-detail-grid.row :label="__('Rechnungsnummer')">{{ $summary['number'] }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Profil')">{{ $summary['profile'] }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Rechnungsdatum')">{{ $summary['issue_date'] ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Fällig am')">{{ $summary['due_date'] ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Verkäufer')">{{ $summary['seller'] ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('USt-IdNr.')">{{ $summary['seller_vat'] ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Netto')">{{ number_format((float) ($summary['net'] ?? 0), 2, ',', '.') }} {{ $summary['currency'] }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Steuer')">{{ number_format((float) ($summary['tax'] ?? 0), 2, ',', '.') }} {{ $summary['currency'] }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Brutto')">{{ number_format((float) ($summary['gross'] ?? 0), 2, ',', '.') }} {{ $summary['currency'] }}</x-detail-grid.row>
            </x-detail-grid>
        </x-card>

        <x-card :title="__('Positionen')">
            @if ($parsed->getLines() === [])
                <x-empty-state icon="receipt_long" :title="__('Keine Positionen im Dokument.')" compact />
            @else
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <th>#</th>
                            <th>{{ __('Bezeichnung') }}</th>
                            <th class="text-right">{{ __('Menge') }}</th>
                            <th class="text-right">{{ __('Einzelpreis') }}</th>
                            <th class="text-right">{{ __('USt %') }}</th>
                            <th class="text-right">{{ __('Netto') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($parsed->getLines() as $line)
                        <tr>
                            <td class="tabular-nums">{{ $line->getId() }}</td>
                            <td>
                                {{ $line->getItemName() }}
                                @if ($line->getItemDescription())
                                    <p class="text-xs text-base-content/60">{{ $line->getItemDescription() }}</p>
                                @endif
                            </td>
                            <td class="text-right tabular-nums">{{ number_format($line->getQuantity(), 2, ',', '.') }}</td>
                            <td class="text-right tabular-nums">{{ number_format((float) $line->getUnitPrice(), 2, ',', '.') }}</td>
                            <td class="text-right tabular-nums">{{ number_format((float) ($line->getTaxPercent() ?? 0), 1, ',', '.') }}</td>
                            <td class="text-right tabular-nums">{{ number_format((float) $line->getNetAmount(), 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>
    @endif

    {{-- Eingangs-Validierung (MVP-166): getrennt von Original/Feldern. --}}
    @if ($incoming !== null && ($incoming->summary['validation'] ?? null) !== null)
        @php($validation = $incoming->summary['validation'])
        <x-card :title="__('Validierung (beim Empfang)')">
            @if ($validation['schema_checked'])
                @if ($validation['schema_errors'] === [])
                    <p class="text-sm text-success">{{ __('UBL-Schema: valide.') }}</p>
                @else
                    <ul class="space-y-1 text-sm">
                        @foreach ($validation['schema_errors'] as $error)
                            <li class="text-error">✖ {{ $error }}</li>
                        @endforeach
                    </ul>
                @endif
            @else
                <p class="text-sm text-base-content/60">{{ __('Kein UBL-Schema anwendbar (z. B. CII) — Regelprüfung siehe KoSIT.') }}</p>
            @endif
            @if (! $validation['kosit_available'])
                <p class="mt-1 text-sm text-warning">{{ __('KoSIT-Validator nicht verfügbar — Regelprüfung wurde nicht durchgeführt.') }}</p>
            @elseif ($validation['kosit_valid'])
                <p class="mt-1 text-sm text-success">{{ __('KoSIT-Prüfung bestanden.') }}</p>
            @else
                <ul class="mt-1 space-y-1 text-sm">
                    @foreach ($validation['kosit_errors'] as $error)
                        <li class="text-error">✖ {{ $error }}</li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    @endif

    {{-- Zuordnungs-Vorschläge + Abweichungen (MVP-167): nur Hinweise — keine Auto-Übernahme. --}}
    @if ($incoming !== null && (($incoming->summary['deviations'] ?? []) !== [] || array_filter((array) ($incoming->summary['suggestions'] ?? [])) !== []))
        <x-card :title="__('Zuordnung und Abweichungen (beim Empfang)')">
            @foreach ((array) ($incoming->summary['deviations'] ?? []) as $deviation)
                <div class="alert alert-warning text-sm">
                    <span class="material-symbols-outlined" aria-hidden="true">warning</span>
                    {{ $deviation }}
                </div>
            @endforeach
            @php($suggestions = (array) ($incoming->summary['suggestions'] ?? []))
            <x-detail-grid>
                @if (($suggestions['suppliers'] ?? []) !== [])
                    <x-detail-grid.row :label="__('Lieferanten-Vorschlag')">
                        @foreach ($suggestions['suppliers'] as $candidate)
                            <div>{{ $candidate['label'] }} <span class="text-xs text-base-content/60">({{ implode(', ', $candidate['reasons']) }})</span></div>
                        @endforeach
                    </x-detail-grid.row>
                @endif
                @if (($suggestions['purchase_orders'] ?? []) !== [])
                    <x-detail-grid.row :label="__('Bestell-Vorschlag')">
                        @foreach ($suggestions['purchase_orders'] as $candidate)
                            <div>{{ $candidate['label'] }} <span class="text-xs text-base-content/60">({{ implode(', ', $candidate['reasons']) }})</span></div>
                        @endforeach
                    </x-detail-grid.row>
                @endif
                @if (($suggestions['projects'] ?? []) !== [])
                    <x-detail-grid.row :label="__('Projekt-Vorschlag')">
                        @foreach ($suggestions['projects'] as $candidate)
                            <div>{{ $candidate['label'] }} <span class="text-xs text-base-content/60">({{ implode(', ', $candidate['reasons']) }})</span></div>
                        @endforeach
                    </x-detail-grid.row>
                @endif
            </x-detail-grid>
            <p class="mt-2 text-xs text-base-content/60">{{ __('Vorschläge sind unverbindlich — es werden nie automatisch Stammdaten angelegt oder geändert.') }}</p>
        </x-card>
    @endif

    {{-- Prüfbereich (MVP-165/167): Hash-Nachweis + Freigabe-Workflow. --}}
    @if ($incoming !== null)
        <x-card :title="__('Prüfung und Freigabe')">
            <x-detail-grid>
                <x-detail-grid.row :label="__('Status')">{{ $incoming->statusLabel() }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Empfangen')">{{ $incoming->received_at->isoFormat('L LT') }} · {{ $incoming->source }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('SHA-256')"><span class="font-mono text-xs">{{ $incoming->sha256 }}</span></x-detail-grid.row>
                @if ($incoming->decision_note)
                    <x-detail-grid.row :label="__('Anmerkung')">{{ $incoming->decision_note }}</x-detail-grid.row>
                @endif
                @if ($incoming->transferred_at)
                    <x-detail-grid.row :label="__('Buchhaltungs-Übergabe')">{{ $incoming->transferred_at->isoFormat('L LT') }}</x-detail-grid.row>
                @endif
            </x-detail-grid>
            <form method="POST" action="{{ route('finance.incoming-invoices.decide', $incoming) }}" class="mt-2 flex flex-wrap items-end gap-2">
                @csrf
                <select name="decision" class="select select-sm select-bordered" aria-label="{{ __('Entscheidung') }}">
                    <option value="approved">{{ __('Fachlich freigeben') }}</option>
                    <option value="question">{{ __('Rückfrage') }}</option>
                    <option value="rejected">{{ __('Ablehnen') }}</option>
                    <option value="payment_released">{{ __('Zahlung freigeben') }}</option>
                </select>
                <input name="note" maxlength="500" class="input input-sm input-bordered flex-1"
                       aria-label="{{ __('Anmerkung (bei Ablehnung Pflicht)') }}"
                       placeholder="{{ __('Anmerkung (bei Ablehnung Pflicht)') }}">
                <x-icon-btn icon="gavel" tone="primary" size="sm" type="submit" show-label>{{ __('Entscheiden') }}</x-icon-btn>
            </form>
            @if ($incoming->transferred_at === null && in_array($incoming->status, [\App\Models\IncomingEInvoice::STATUS_APPROVED, \App\Models\IncomingEInvoice::STATUS_PAYMENT_RELEASED], true))
                <x-action-form :action="route('finance.incoming-invoices.transfer', $incoming)" class="mt-2"
                      :confirm="__('Eingang an die führende Buchhaltung übergeben? Die Übergabe wird als Nachweis vermerkt.')"
                      confirm-icon="outbox"
                      confirm-tone="primary"
                      :confirm-label="__('Übergeben')">
                    <x-icon-btn icon="outbox" tone="primary" size="sm" type="submit" show-label>{{ __('An Buchhaltung übergeben') }}</x-icon-btn>
                </x-action-form>
            @endif
        </x-card>
    @endif
</x-page-shell>
@endsection
