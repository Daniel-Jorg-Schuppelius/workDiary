{{--
  Created on   : Thu Aug 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : merge-compare.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Lieferanten zusammenführen'))
@section('nav-title', __('Lieferanten zusammenführen'))

@php
    /** @var \App\Models\Supplier\Supplier $source */
    /** @var \App\Models\Supplier\Supplier $target */

    // Reine Anzeigefelder (Identität) — nicht übersteuerbar.
    $identityFields = [
        'name' => __('Name'),
        'number' => __('Nummer'),
    ];
    // Übersteuerbare Felder — müssen mit SupplierMergeService::FILLABLE_FROM_SOURCE
    // übereinstimmen, sonst ignoriert der Service die Auswahl.
    $overridableFields = [
        'company' => __('Firma'),
        'vendor_number' => __('Lieferantennr.'),
        'vat_id' => __('USt-IdNr.'),
        'tax_number' => __('Steuernr.'),
        'contact_name' => __('Ansprechpartner'),
        'email' => __('E-Mail'),
        'phone' => __('Telefon'),
        'mobile' => __('Mobil'),
        'homepage' => __('Webseite'),
        'address_street' => __('Straße'),
        'address_zip' => __('PLZ'),
        'address_city' => __('Ort'),
        'country' => __('Land'),
        'comment' => __('Notiz'),
        'bank_account_holder' => __('Kontoinhaber'),
        'bank_iban' => __('IBAN'),
        'bank_bic' => __('BIC'),
    ];
@endphp

@section('content')
@include('stammdaten._merge_compare', [
    'routePrefix' => 'suppliers',
    'subtitle' => __('Wählen Sie pro Feld, ob der Wert des zu löschenden Lieferanten den Ziel-Wert ersetzen soll. Nicht angehakte, leere Ziel-Felder werden ohnehin aus der Quelle aufgefüllt; befüllte Ziel-Felder bleiben unangetastet.'),
    'confirm' => __('„:source“ endgültig in „:target“ zusammenführen? Der Quell-Lieferant wird gelöscht.', ['source' => $source->name, 'target' => $target->name]),
])
@endsection
