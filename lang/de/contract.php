<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : contract.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'template' => [
        'title' => 'Vertragsvorlagen',
        'subtitle' => 'Vorlagen entstehen an einem Vertrag über „Als Vorlage speichern“ oder aus einem Branchenprofil.',
        'name' => 'Name',
        'obligations' => 'Pflichten',
        'active' => 'Aktiv',
        'edit' => 'Vorlage bearbeiten',
        'delete' => 'Vorlage löschen',
        'confirm_delete' => 'Vorlage „:name“ löschen? Bestehende Verträge bleiben unverändert.',
        'empty_title' => 'Keine Vertragsvorlagen',
        'empty' => 'Öffnen Sie einen Vertrag und wählen Sie „Als Vorlage speichern“.',
        'use' => 'Vorlage:',
        'save_title' => 'Als Vorlage speichern',
        'save' => 'Vorlage speichern',
        'save_hint' => 'Übernommen werden Vertragsart, Titel, Laufzeit, Kündigung, Verlängerung, Wertbezug, Anpassungsregel und die Pflichten (fällig relativ zum Vertragsbeginn). Partner, Beträge und Termine bleiben außen vor.',
        'flash' => [
            'created' => 'Vorlage „:name“ gespeichert.',
            'updated' => 'Vorlage gespeichert.',
            'deleted' => 'Vorlage gelöscht.',
        ],
    ],
    'cost_center' => [
        'title' => 'Vertragswerte je Kostenstelle',
        'subtitle' => 'Laufende Verträge (aktiv oder gekündigt, aber nicht beendet); wiederkehrende Werte auf Jahr und Monat gerechnet, Einmalwerte getrennt. Plan-Sicht, keine Buchung.',
        'field' => 'Kostenstelle',
        'count' => 'Verträge',
        'yearly' => 'Jährlich',
        'monthly' => 'Monatlich',
        'once' => 'Einmalig',
        'none' => 'Ohne Kostenstelle',
        'empty' => 'Keine laufenden Verträge.',
    ],
    'extraction' => [
        'title' => 'Vorschläge aus „:document“',
        'check' => 'Erkannte Angaben sind vorbelegt. Bitte mit dem Dokument vergleichen; übernommen wird erst beim Speichern.',
        'none' => 'Im Dokument wurden keine Vertragsangaben erkannt (oder der Text war nicht lesbar).',
        'create' => 'Vertrag aus Dokument',
        'leasing_create' => 'Leasingakte aus Dokument',
        'field' => [
            'rate_amount' => 'Rate',
            'payment_rhythm' => 'Zahlungsrhythmus',
            'special_payment' => 'Sonderzahlung',
            'residual_value' => 'Restwert',
            'purchase_option_amount' => 'Kaufoption',
            'starts_on' => 'Beginn',
            'ends_on' => 'Ende',
            'min_term_months' => 'Mindestlaufzeit',
            'notice_period_days' => 'Kündigungsfrist (in Tage umgerechnet)',
            'renew_period_months' => 'Verlängerung',
            'auto_renew' => 'Automatische Verlängerung',
            'value_amount' => 'Vertragswert',
            'value_period' => 'Wertbezug',
        ],
    ],
];
