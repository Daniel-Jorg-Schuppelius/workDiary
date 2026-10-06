{{--
  Created on   : Thu Aug 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : duplicates.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Artikel-Abgleich'))
@section('nav-title', __('Artikel-Abgleich'))

@php
    $reasonLabels = [
        'vat_id' => __('USt-IdNr.'),
        'vendor_number' => __('Artikelnr.'),
        'email' => __('E-Mail'),
        'company_zip' => __('Firma + PLZ'),
        'name' => __('Name/Firma ähnlich'),
    ];
    // Felder, die im Vergleich gegenübergestellt werden.
    $compareFields = [
        'name' => __('Name'),
        'number' => __('Artikelnummer'),
        'gtin' => __('GTIN/EAN'),
        'base_unit' => __('Basiseinheit'),
        'category' => __('Kategorie'),
    ];
@endphp

@section('content')
    @include('stammdaten._duplicates', [
        'finder' => \App\Services\Stammdaten\ArticleDuplicateFinder::class,
        'routePrefix' => 'articles',
        'records' => $articles,
        'counters' => [__('Varianten') => 'variants_count'],
        'texts' => [
            'subtitle' => __('Doppelte Artikel (z. B. aus Katalog-Adoption, CSV-Import und manueller Anlage) werden hier gegenübergestellt. Varianten wandern samt Bestandshistorie als Ganzes zum Ziel-Artikel — Bestände werden nie vermischt. Läuft eine Inventur oder ein Fertigungsauftrag, wird das Zusammenführen abgelehnt.'),
            'manual_hint' => __('— zwei Artikel frei wählen (für Dubletten, die der Abgleich nicht erkennt)'),
            'target' => __('Ziel-Artikel'),
            'source' => __('Quell-Artikel'),
            'bulk_confirm' => __('Alle ausgewählten Paare zusammenführen? Die jeweils markierten Quell-Artikel werden gelöscht — das kann nicht rückgängig gemacht werden.'),
            'merge_confirm' => __('„:source“ endgültig in „:target“ zusammenführen? Der Quell-Artikel wird gelöscht.'),
            'swap_confirm' => __('Richtung tauschen: „:source“ in „:target“ zusammenführen? Der Quell-Artikel wird gelöscht.'),
        ],
    ])
@endsection
