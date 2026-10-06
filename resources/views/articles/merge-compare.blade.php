{{--
  Created on   : Thu Aug 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : merge-compare.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Artikel zusammenführen'))
@section('nav-title', __('Artikel zusammenführen'))

@php
    /** @var \App\Models\Article\Article $source */
    /** @var \App\Models\Article\Article $target */

    // Reine Anzeigefelder (Identität) — nicht übersteuerbar.
    $identityFields = [
        'name' => __('Name'),
        'number' => __('Artikelnummer'),
        'gtin' => __('GTIN/EAN'),
        'base_unit' => __('Basiseinheit'),
    ];
    // Übersteuerbare Felder — müssen mit ArticleMergeService::FILLABLE_FROM_SOURCE
    // übereinstimmen, sonst ignoriert der Service die Auswahl.
    $overridableFields = [
        'description' => __('Beschreibung'),
        'category' => __('Kategorie'),
        'subcategory' => __('Unterkategorie'),
        'assembly_minutes' => __('Montagezeit (Min.)'),
        'copper_weight' => __('Kupfergewicht'),
        'copper_base_price' => __('Kupfer-Basispreis'),
        'valuation_method' => __('Bewertungsverfahren'),
        'serial_scheme' => __('Seriennummern-Schema'),
        'customs_tariff_number' => __('article.field.customs_tariff_number'),
        'origin_country' => __('article.field.origin_country'),
        'net_weight_kg' => __('article.field.net_weight_kg'),
    ];
@endphp

@section('content')
@include('stammdaten._merge_compare', [
    'routePrefix' => 'articles',
    'subtitle' => __('Wählen Sie pro Feld, ob der Wert des zu löschenden Artikels den Ziel-Wert ersetzen soll. Nicht angehakte, leere Ziel-Felder werden ohnehin aus der Quelle aufgefüllt; befüllte Ziel-Felder bleiben unangetastet.'),
    'confirm' => __('„:source“ endgültig in „:target“ zusammenführen? Der Quell-Artikel wird gelöscht.', ['source' => $source->name, 'target' => $target->name]),
])
@endsection
