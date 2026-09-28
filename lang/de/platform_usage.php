<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : platform_usage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Nutzung je Mandant und Branchenvergleich (MVP-951/949).
return [
    'title' => 'Nutzung je Mandant',
    'subtitle' => 'Nutzer, Speicher, Module und letzte Aktivität je Organisation — nur für den Plattformbetrieb.',
    'back' => 'Organisationen',
    'empty' => 'Keine Organisationen vorhanden.',
    'field' => [
        'organization' => 'Organisation',
        'status' => 'Status',
        'users' => 'Nutzer',
        'active_users' => 'Aktiv (30 Tage)',
        'storage' => 'Speicher',
        'modules' => 'Module',
        'last_activity' => 'Letzte Aktivität',
    ],
    'benchmark' => [
        'link' => 'Branchenvergleich',
        'subtitle' => 'Jahresemissionen je Hauptbranchenprofil über alle Mandanten ohne Demo, nur ab drei Organisationen je Branche.',
        'title' => 'Emissionen je Branche :year (anonym)',
        'branch' => 'Branche',
        'organizations' => 'Organisationen',
        'mean' => 'Mittelwert',
        'median' => 'Median',
        'empty' => 'Keine Branche mit mindestens :min Organisationen und erfassten Emissionen.',
    ],
    // Nutzungsabrechnung, Abrechnungsdaten und Tarifanfragen (MVP-956/957).
    'billing' => [
        'title' => 'Nutzungsabrechnung',
        'subtitle' => 'Monatsstand je Organisation, bewertet mit den Einheitspreisen aus den Systemeinstellungen (platform_billing.*). Es entsteht keine Rechnung.',
        'month' => 'Monat',
        'plan' => 'Tarif',
        'amount' => 'Betrag',
        'empty' => 'Noch kein Monatsstand. Der Stand entsteht am Monatsersten (platform:usage-snapshot).',
        'note' => 'Betrag = Grundgebühr + Nutzer + aktive Nutzer + angefangene GB Speicher, jeweils mal Einheitspreis.',
    ],
    'plan' => [
        'free' => 'Free',
        'pro' => 'Pro',
        'enterprise' => 'Enterprise',
    ],
    'billing_profile' => [
        'title' => 'Abrechnungsdaten',
        'subtitle' => 'Rechnungsempfänger für die Nutzung der Software und Tarifwechsel.',
        'contact' => 'Rechnungsempfänger',
        'save' => 'Speichern',
        'invalid_vat' => 'Die USt-IdNr. ist ungültig.',
        'field' => [
            'name' => 'Name / Firma',
            'email' => 'E-Mail für Rechnungen',
            'street' => 'Straße',
            'zip' => 'PLZ',
            'city' => 'Ort',
            'country' => 'Land (ISO)',
            'vat_id' => 'USt-IdNr.',
            'reference' => 'Bestellzeichen',
        ],
        'hint' => [
            'reference' => 'Erscheint auf den Rechnungen des Betreibers.',
        ],
        'flash' => [
            'saved' => 'Abrechnungsdaten gespeichert.',
        ],
    ],
    'plan_request' => [
        'title' => 'Tarifwechsel anfragen',
        'open_title' => 'Offene Tarifanfragen',
        'history' => 'Anfragen',
        'current' => 'Aktueller Tarif: :plan',
        'send' => 'Anfrage senden',
        'withdraw' => 'Zurückziehen',
        'done' => 'Erledigt',
        'decline' => 'Ablehnen',
        'empty' => 'Keine Anfragen.',
        'already_open' => 'Es gibt bereits eine offene Anfrage.',
        'field' => [
            'plan' => 'Gewünschter Tarif',
            'addons' => 'Zusatzmodule',
            'note' => 'Anmerkung',
            'requester' => 'Angefragt von',
            'created_at' => 'Datum',
        ],
        'hint' => [
            'addons' => 'Modulcodes durch Komma getrennt, z. B. module.rental',
        ],
        'status' => [
            'open' => 'Offen',
            'done' => 'Erledigt',
            'declined' => 'Abgelehnt',
            'withdrawn' => 'Zurückgezogen',
        ],
        'flash' => [
            'sent' => 'Anfrage gesendet. Der Betreiber meldet sich.',
            'withdrawn' => 'Anfrage zurückgezogen.',
            'decided' => 'Anfrage geschlossen.',
        ],
    ],
];
