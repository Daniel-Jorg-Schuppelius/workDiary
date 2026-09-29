{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', $supplier->name . ' — ' . __('Lieferant'))
@section('nav-title', $supplier->name)

@section('content')
<x-page-shell>
    {{-- Header --}}
    <x-entity-header :title="$supplier->name" :color="$supplier->color"
                     :back-route="route('suppliers.index')"
                     :edit-route="route('suppliers.edit', $supplier)"
                     :archived="$supplier->isArchived()"
                     :restore-route="route('suppliers.restore', $supplier)"
                     :archive-route="route('suppliers.archive', $supplier)"
                     :can-manage="auth()->user()->can('update', $supplier)">
        <x-slot:badges>
            @if ($supplier->isArchived())
                <x-status-badge tone="ghost">{{ __('archiviert') }}</x-status-badge>
            @endif
            @unless ($supplier->active)
                <x-status-badge tone="warning">{{ __('inaktiv') }}</x-status-badge>
            @endunless
        </x-slot:badges>
        <x-slot:meta>
            @if ($supplier->company){{ $supplier->company }} · @endif
            @if ($supplier->number){{ __('Nr.') }} {{ $supplier->number }} · @endif
            {{ $supplier->currency->value }}
        </x-slot:meta>
        @if ($tags->isNotEmpty())
            <x-slot:tags>
                @foreach ($tags as $tag)
                    <x-tag-badge :tag="$tag" />
                @endforeach
            </x-slot:tags>
        @endif
    </x-entity-header>

    <x-identifier-issues :issues="$identifierIssues ?? []" />

    {{-- KPI-Kacheln (analog Kunden-Detailseite); nur mit aktivem Lager-Modul. --}}
    @if (($procurementStats ?? null) !== null)
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <x-kpi-tile :label="__('Artikel (Bezugsquellen)')" :value="$procurementStats['articles']" tone="neutral" />
            <x-kpi-tile :label="__('Bestellungen')" :value="$procurementStats['orders']" tone="neutral" />
            <x-kpi-tile :label="__('Offene Bestellungen')" :value="$procurementStats['open_orders']" tone="neutral" />
        </div>
    @endif

    {{-- Kompakte Trends im gewählten Zeitraum: Ausgaben & Belegzahl — zwei
         zeitlich ausgerichtete Diagramme als Gegenüberstellung (analog Kunde);
         Finanzsicht, nur für Auswertungsberechtigte. --}}
    @if (($spendSeries ?? null) !== null)
        <div class="chart-grid grid grid-cols-1 gap-3 xl:grid-cols-2">
            <x-charts.bar :title="__('Ausgaben :per', ['per' => $periodPhrase])" unit="€" :series="$spendSeries"
                          :x-label="$periodAxis" :y-label="__('Ausgaben')"
                          :note="__('Einkaufsbelege dieses Lieferanten im Zeitraum; Gutschriften mindern.')" />
            <x-charts.bar :title="__('Belege :per', ['per' => $periodPhrase])" :unit="__('Belege')" :series="$voucherCountSeries"
                          :x-label="$periodAxis" :y-label="__('Belege')"
                          :note="__('Anzahl Einkaufsbelege dieses Lieferanten im Zeitraum.')" />
        </div>
    @endif

    {{-- Stammdaten --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-card :title="__('Kontakt')" icon="contacts">
            <x-detail-grid>
                <x-detail-grid.row :label="__('Ansprechpartner')" :value="$supplier->contact_name" />
                <x-detail-grid.row :label="__('E-Mail')">@if ($supplier->email)<a class="link" href="mailto:{{ $supplier->email }}">{{ $supplier->email }}</a>@endif</x-detail-grid.row>
                <x-detail-grid.row :label="__('Telefon')" :value="$supplier->phone" />
                <x-detail-grid.row :label="__('Mobil')" :value="$supplier->mobile" />
                <x-detail-grid.row :label="__('Homepage')"><x-external-link :url="$supplier->homepage" /></x-detail-grid.row>
                @if ($supplier->address_street || $supplier->address_zip || $supplier->address_city)
                    <x-detail-grid.row :label="__('Adresse')" class="whitespace-pre-line">{!! e($supplier->address_street) !!}@if($supplier->address_street)
@endif{{ trim(($supplier->address_zip ?? '').' '.($supplier->address_city ?? '')) }}</x-detail-grid.row>
                @elseif ($supplier->address)
                    <x-detail-grid.row :label="__('Adresse')" class="whitespace-pre-line">{{ $supplier->address }}</x-detail-grid.row>
                @endif
                <x-detail-grid.row :label="__('Land')" :value="$supplier->country ? (\CommonToolkit\Enums\CountryCode::tryFrom($supplier->country)?->getLabel(app()->getLocale()) ?? $supplier->country) : null" />
            </x-detail-grid>
            <x-contact-persons :persons="$supplier->contact_persons" />
        </x-card>

        <x-card :title="__('Geschäftsdaten')" icon="store">
            <x-detail-grid>
                <x-detail-grid.row :label="__('Aktiv')" :value="$supplier->active ? __('Ja') : __('Nein')" />
                <x-detail-grid.row :label="__('USt-IdNr.')" :value="$supplier->vat_id" />
                <x-detail-grid.row :label="__('Kreditorennummer')" :value="$supplier->vendor_number" />
                <x-detail-grid.row :label="__('Währung')" :value="$supplier->currency->value" />
                <x-detail-grid.row :label="__('Zeitzone')" :value="$supplier->timezone" />
            </x-detail-grid>
            @php $bank = $supplier->bankDetails(); @endphp
            @if ($bank['has_any'])
                <div class="pt-3 border-t border-base-300">
                    <h3 class="mb-1 text-sm font-semibold">{{ __('Bankverbindung') }}</h3>
                    <x-detail-grid>
                        <x-detail-grid.row :label="__('Kontoinhaber')" :value="$bank['holder']" />
                        <x-detail-grid.row :label="__('IBAN')" :value="$bank['iban']" class="tabular-nums" />
                        <x-detail-grid.row :label="__('BIC')" :value="$bank['bic']" />
                        <x-detail-grid.row :label="__('Bank')" :value="$bank['bank']" />
                    </x-detail-grid>
                </div>
            @endif
            @if ($supplier->comment)
                <div class="pt-3 text-sm">
                    <div class="text-muted">{{ __('Notiz') }}</div>
                    <p class="whitespace-pre-line">{{ $supplier->comment }}</p>
                </div>
            @endif
        </x-card>
    </div>

    {{-- Selbstauskunft (MVP-937) --}}
    <x-card :title="__('supplier_questionnaire.card')" icon="fact_check">
        @can(\App\Enums\User\Permission::SupplierUpdate->value)
            <x-slot:actions>
                <x-icon-btn icon="send" size="sm" data-entry-modal-trigger :href="route('supplier-questionnaires.send-form', $supplier)" show-label>{{ __('supplier_questionnaire.send') }}</x-icon-btn>
            </x-slot:actions>
        @endcan
        @if ($questionnaireRequests->isEmpty())
            <p class="text-sm text-muted">{{ __('supplier_questionnaire.none') }}</p>
        @else
            <ul class="divide-y divide-base-200 text-sm">
                @foreach ($questionnaireRequests as $qr)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-1">
                        <span>{{ $qr->questionnaire?->name }} · {{ $qr->sent_at?->format('d.m.Y') }}</span>
                        <span class="flex items-center gap-2">
                            <span class="wd-badge badge-ghost">{{ $qr->status->label() }}</span>
                            @if ($qr->valid_until)<span class="text-xs text-muted">{{ __('supplier_questionnaire.valid_until', ['date' => $qr->valid_until->format('d.m.Y')]) }}</span>@endif
                            <x-icon-btn icon="visibility" size="xs" data-entry-modal-trigger :href="route('supplier-questionnaires.requests.show', $qr)" :label="__('supplier_questionnaire.open')" />
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    {{-- Plugin-Panels (MVP-1039), z. B. Verknüpfung mit dem Buchhaltungsprogramm. --}}
    {!! app(\App\Plugins\PluginManager::class)->renderSlot('supplier-show.panels', $supplier) !!}

    {{-- Anhänge --}}
    <x-attachments-section :attachments="$attachments" upload-type="supplier"
                           :upload-id="$supplier->sqid" :can-upload="auth()->user()->can('update', $supplier)" />

    {{-- Rechnungen & Belege (Eingangsrechnungen/Aufträge/Angebote …), zeitraumgefiltert.
         Lieferanten haben keine lokalen Rechnungen → nur Belege der Buchhaltungsprogramme. --}}
    @include('partials._vouchers', [
        'invoices' => collect(),
        'external' => $externalDocuments,
        'range' => $voucherRange,
    ])

    {{-- Kommunikation (MVP-1023): Absprachen, Anrufe, Zusagen zum Lieferanten. --}}
    @include('communication-notes._panel', ['notable' => $supplier, 'notableKind' => 'supplier'])

    {{-- Änderungsverlauf (Audit) — Benennung konsistent zu Kunde/Fremdkunde --}}
    @if ($auditLogs->isNotEmpty())
    <x-card :title="__('Änderungsverlauf')" icon="history">
        <x-audit-log-list :logs="$auditLogs" />
    </x-card>
    @endif
</x-page-shell>
@endsection
