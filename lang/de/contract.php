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
    // Verbraucherpreisindex und Indexanpassung (MVP-952).
    'price_index' => [
        'title' => 'Verbraucherpreisindex',
        'subtitle' => 'VPI Deutschland (Basis 2020 = 100) aus der Bundesbank-Zeitreihe. Neue und geänderte Werte gelten erst nach Freigabe.',
        'pending' => 'Freigabe ausstehend: :count Werte',
        'add' => 'Wert nachtragen',
        'approve' => 'Freigeben',
        'reject' => 'Verwerfen',
        'empty' => 'Noch keine Indexwerte. Der Abruf läuft monatlich (contracts:price-index-sync).',
        'field' => [
            'period' => 'Monat',
            'value' => 'Indexwert',
            'source' => 'Quelle',
        ],
        'source' => [
            'bundesbank' => 'Bundesbank',
            'manual' => 'Von Hand',
        ],
        'status' => [
            'pending' => 'Freigabe offen',
            'approved' => 'Freigegeben',
            'rejected' => 'Verworfen',
        ],
        'flash' => [
            'approved' => 'Indexwert freigegeben.',
            'rejected' => 'Indexwert verworfen.',
            'saved' => 'Indexwert gespeichert und freigegeben.',
        ],
    ],
    'indexation' => [
        'title' => 'Indexanpassung (VPI)',
        'configure' => 'Wertsicherung',
        'check' => 'Jetzt prüfen',
        'save' => 'Speichern',
        'apply' => 'Übernehmen',
        'dismiss' => 'Verwerfen',
        'not_configured' => 'Kein Basisindex hinterlegt. Tragen Sie Basisindex und Basismonat aus der Wertsicherungsklausel ein.',
        'base_line' => 'Basisindex :value (:period)',
        'preview_line' => 'aktuell :value (:period), :change % → :amount :currency',
        'effective' => 'wirksam ab :date',
        'confirm_apply' => 'Vertragswert auf :amount setzen und den Basisindex fortschreiben?',
        'superseded' => 'Durch einen neueren Indexstand abgelöst.',
        'notification' => 'Indexanpassung vorgeschlagen: :number',
        'disclaimer' => 'Die Rechnung folgt den hinterlegten Angaben. Sie ersetzt keine Prüfung der Wertsicherungsklausel.',
        'section' => [
            'base' => 'Basis und Regel',
        ],
        'field' => [
            'base_value' => 'Basisindex',
            'base_period' => 'Basismonat',
            'threshold' => 'Schwelle (%)',
            'pass_through' => 'Weitergabe (%)',
            'index' => 'Index',
            'change' => 'Veränderung',
            'old_amount' => 'Bisher',
            'new_amount' => 'Neu',
        ],
        'hint' => [
            'base_value' => 'Indexstand bei Vertragsschluss oder letzter Anpassung, Basis 2020 = 100.',
            'threshold' => 'Anpassung erst ab dieser Veränderung, leer = jede Veränderung.',
            'pass_through' => 'Anteil der Veränderung, der weitergegeben wird, leer = 100 %.',
        ],
        'status' => [
            'proposed' => 'Vorschlag',
            'applied' => 'Übernommen',
            'dismissed' => 'Verworfen',
        ],
        'flash' => [
            'saved' => 'Wertsicherung gespeichert.',
            'proposed' => 'Anpassungsvorschlag angelegt.',
            'none' => 'Kein Vorschlag: kein neuer freigegebener Indexstand oder Schwelle nicht erreicht.',
            'applied' => 'Indexanpassung übernommen.',
            'dismissed' => 'Indexanpassung verworfen.',
        ],
    ],
];
