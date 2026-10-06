{{--
  Created on   : Tue Jun 30 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : merge-compare.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Kunden zusammenführen'))
@section('nav-title', __('Kunden zusammenführen'))

@php
    /** @var \App\Models\Customer\Customer $source */
    /** @var \App\Models\Customer\Customer $target */

    // Reine Anzeigefelder (Identität) — nicht übersteuerbar.
    $identityFields = [
        'name' => __('Name'),
        'number' => __('Kundennr.'),
    ];
    // Übersteuerbare Felder — müssen mit CustomerMergeService::FILLABLE_FROM_SOURCE
    // übereinstimmen, sonst ignoriert der Service die Auswahl.
    $overridableFields = [
        'company' => __('Firma'),
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
        'hourly_rate' => __('Stundensatz'),
        'internal_rate' => __('Interner Satz'),
        'comment' => __('Notiz'),
        'invoice_text' => __('Rechnungstext'),
        'bank_iban' => __('IBAN'),
        'debtor_no' => __('Debitor-Nr.'),
    ];
@endphp

@section('content')
@include('stammdaten._merge_compare', [
    'routePrefix' => 'customers',
    'subtitle' => __('Wählen Sie pro Feld, ob der Wert des zu löschenden Kunden den Ziel-Wert ersetzen soll. Nicht angehakte, leere Ziel-Felder werden ohnehin aus der Quelle aufgefüllt; befüllte Ziel-Felder bleiben unangetastet.'),
    'confirm' => __('„:source“ endgültig in „:target“ zusammenführen? Der Quell-Kunde wird gelöscht.', ['source' => $source->name, 'target' => $target->name]),
])
@endsection
