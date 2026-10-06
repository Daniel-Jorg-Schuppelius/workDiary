{{--
  Created on   : Tue Jun 30 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : duplicates.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Kunden-Abgleich'))
@section('nav-title', __('Kunden-Abgleich'))

@php
    $reasonLabels = [
        'vat_id' => __('USt-IdNr.'),
        'accounting_number' => __('stammdaten.accounting_number'),
        'email' => __('E-Mail'),
        'company_zip' => __('Firma + PLZ'),
        'name' => __('Name/Firma ähnlich'),
    ];
    // Felder, die im Vergleich gegenübergestellt werden.
    $compareFields = [
        'name' => __('Name'),
        'company' => __('Firma'),
        'number' => __('Kundennr.'),
        'accounting_number' => __('stammdaten.accounting_number'),
        'vat_id' => __('USt-IdNr.'),
        'email' => __('E-Mail'),
        'address_zip' => __('PLZ'),
        'address_city' => __('Ort'),
    ];
@endphp

@section('content')
    @include('stammdaten._duplicates', [
        'finder' => \App\Services\Stammdaten\CustomerDuplicateFinder::class,
        'routePrefix' => 'customers',
        'records' => $customers,
        'counters' => [__('Projekte') => 'projects_count'],
        'texts' => [
            'subtitle' => __('Doppelte Kunden (z. B. nach dem Toggl-Import) werden hier gegenübergestellt. Pro Paar entscheiden Sie, welcher Datensatz bestehen bleibt — alle Projekte, Zeiten, Rechnungen und Referenzen werden auf ihn umgehängt, der andere wird gelöscht.'),
            'manual_hint' => __('— zwei Kunden frei wählen (für Dubletten, die der Abgleich nicht erkennt)'),
            'target' => __('Ziel-Kunde'),
            'source' => __('Quell-Kunde'),
            'bulk_confirm' => __('Alle ausgewählten Paare zusammenführen? Die jeweils markierten Quell-Kunden werden gelöscht — das kann nicht rückgängig gemacht werden.'),
            'merge_confirm' => __('„:source“ endgültig in „:target“ zusammenführen? Der Quell-Kunde wird gelöscht.'),
            'swap_confirm' => __('Richtung tauschen: „:source“ in „:target“ zusammenführen? Der Quell-Kunde wird gelöscht.'),
        ],
    ])
@endsection
