<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : passenger.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Personenbeförderung (MVP-456, Branchenprofil Taxi/Mietwagen).
return [
    'entry_title' => 'Fahrtauftrag (:mode)',
    'entry_content' => 'Personenbeförderung — Bestellkanal: :channel. Fachdaten in der Fahrtakte.',

    'error' => [
        'destination_required' => 'Ziel angeben oder Zielfreiheit ausdrücklich bestätigen.',
        'receipt_channel_invalid' => 'Mietwagen-/Bedarfsverkehr braucht einen nachweisbaren Auftragseingang am Betriebssitz — Winkkunden sind unzulässig (§ 49 Abs. 4 PBefG).',
        'pickup_required' => 'Abholort ist Pflicht.',
        'not_assigned' => 'Fahrtbeginn erst nach Disposition (Fahrer, Fahrzeug, Konzession).',
        'tariff_required' => 'Taxenverkehr fährt zum behördlichen Tarif — bitte Tarif wählen.',
        'fixed_price_outside_corridor' => 'Der Festpreis liegt außerhalb des behördlich zulässigen Korridors.',
        'meter_value_required' => 'Taxameter-/Gerätewert ist Pflicht beim Fahrtabschluss.',
        'tax_decision_required' => 'Steuerentscheidung (Steuersatz) ist Pflicht beim Fahrtabschluss.',
        'payment_required' => 'Zahlungsart ist Pflicht beim Fahrtabschluss.',
        'invalid_transition' => 'Unzulässiger Statuswechsel.',
        'invalid_transition_detail' => 'Unzulässiger Statuswechsel: :from → :to.',
        'return_not_applicable' => 'Rückkehrnachweis gilt nur für Mietwagenverkehr.',
    ],

    'issue' => [
        'driver_unqualified' => 'Fahrerlaubnis zur Fahrgastbeförderung fehlt oder ist abgelaufen.',
        'concession_missing' => 'Keine gültige Konzession für diese Betriebsart.',
        'vehicle_profile_missing' => 'Fahrzeug hat kein Personenbeförderungs-Profil.',
        'vehicle_mode_unsupported' => 'Fahrzeug ist für diese Betriebsart nicht zugelassen.',
        'vehicle_proofs_expired' => 'Fahrzeugnachweise abgelaufen (Eichung/BOKraft/HU).',
        'vehicle_not_barrier_free' => 'Fahrt erfordert Barrierefreiheit — Fahrzeug erfüllt sie nicht.',
        'vehicle_no_wheelchair_place' => 'Fahrt erfordert einen Rollstuhlplatz — Fahrzeug hat keinen.',
        'vehicle_too_small' => 'Fahrgastanzahl übersteigt die Sitzplätze des Fahrzeugs.',
    ],

    'proof' => [
        'meter_calibration' => 'Eichung Taxameter/Wegstreckenzähler',
        'bokraft' => 'BOKraft-Prüfung',
        'hu' => 'Hauptuntersuchung',
    ],
];
