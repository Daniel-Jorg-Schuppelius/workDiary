{{--
  Created on   : Thu Aug 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : duplicates.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Lieferanten-Abgleich'))
@section('nav-title', __('Lieferanten-Abgleich'))

@php
    $reasonLabels = [
        'vat_id' => __('USt-IdNr.'),
        'vendor_number' => __('Lieferantennr.'),
        'email' => __('E-Mail'),
        'company_zip' => __('Firma + PLZ'),
        'name' => __('Name/Firma ähnlich'),
    ];
    // Felder, die im Vergleich gegenübergestellt werden.
    $compareFields = [
        'name' => __('Name'),
        'company' => __('Firma'),
        'number' => __('Nummer'),
        'vendor_number' => __('Lieferantennr.'),
        'vat_id' => __('USt-IdNr.'),
        'email' => __('E-Mail'),
        'address_zip' => __('PLZ'),
        'address_city' => __('Ort'),
    ];
@endphp

@section('content')
    @include('stammdaten._duplicates', [
        'finder' => \App\Services\Stammdaten\SupplierDuplicateFinder::class,
        'routePrefix' => 'suppliers',
        'records' => $suppliers,
        'counters' => [__('Bestellungen') => 'purchase_orders_count'],
        'texts' => [
            'subtitle' => __('Doppelte Lieferanten (z. B. aus Import, Lexoffice-Sync und manueller Anlage) werden hier gegenübergestellt. Pro Paar entscheiden Sie, welcher Datensatz bestehen bleibt — Bestellungen, Kataloge, Eingangsrechnungen und Referenzen werden auf ihn umgehängt, der andere wird gelöscht.'),
            'manual_hint' => __('— zwei Lieferanten frei wählen (für Dubletten, die der Abgleich nicht erkennt)'),
            'target' => __('Ziel-Lieferant'),
            'source' => __('Quell-Lieferant'),
            'bulk_confirm' => __('Alle ausgewählten Paare zusammenführen? Die jeweils markierten Quell-Lieferanten werden gelöscht — das kann nicht rückgängig gemacht werden.'),
            'merge_confirm' => __('„:source“ endgültig in „:target“ zusammenführen? Der Quell-Lieferant wird gelöscht.'),
            'swap_confirm' => __('Richtung tauschen: „:source“ in „:target“ zusammenführen? Der Quell-Lieferant wird gelöscht.'),
        ],
    ])
@endsection
