<?php
/*
 * Created on   : Fri Jun 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : day-close.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Tagesabschluss (MVP-015, docs/tagesabschluss.md) — Seitentexte,
 * Validator-Meldungen (§4), Flash- und Fehlertexte. Paritätisch in
 * de/en/fr/it/es gepflegt; Enum-Labels liegen in enums.php
 * (dayClosure.status / dayCorrection.status).
 */

return [
    'title' => 'Tagesabschluss',
    'title_day' => 'Tagesabschluss :day',

    'subtitle' => [
        'own' => 'Tag prüfen, Lücken nachtragen und als vollständig erfasst abschließen.',
        'other' => 'Tagesabschluss von :name.',
    ],

    'section' => [
        'attendance' => 'Anwesenheit',
        'breaks' => 'Pausen',
        'entries' => 'Auftrags- und Projektzeiten',
        'issues' => 'Lücken & Warnungen',
        'balance' => 'Bilanz',
        'corrections' => 'Korrekturanträge',
    ],

    'field' => [
        'date' => 'Datum',
        'recorded_break' => 'Erfasste Pause',
        'required_break' => 'Pflichtpause',
        'target' => 'Soll-Stunden',
        'gross' => 'Anwesenheit (brutto)',
        'break' => 'Pause',
        'net' => 'Netto-Arbeit',
        'booked' => 'Verbucht',
        'diff' => 'Differenz',
        'day_balance' => 'Saldo Tag',
        'month_balance' => 'Saldo lfd. Monat',
        'duration' => 'Dauer',
        'project' => 'Auftrag / Projekt',
        'activity' => 'Aktivität',
        'comment' => 'Kommentar',
        'billable' => 'Abrechenbar',
        'reason' => 'Begründung',
        'reason_placeholder' => 'Begründung (mind. :min Zeichen)',
        'decision' => 'Entscheidung',
    ],

    'action' => [
        'prev_day' => 'Vorheriger Tag',
        'next_day' => 'Nächster Tag',
        'today' => 'Heute',
        'pick_date' => 'Datum wählen',
        'show_day' => 'Tag anzeigen',
        'clock_in' => 'Jetzt stempeln',
        'clock_out' => 'Jetzt ausstempeln',
        'book_time' => 'Zeit buchen',
        'save' => 'Speichern',
        'close_day' => 'Tag abschließen',
        'request_correction' => 'Korrektur anfordern',
        'reopen' => 'Tag wieder öffnen',
        'approve' => 'Freigeben',
        'reject' => 'Ablehnen',
        'cancel' => 'Abbrechen',
    ],

    'status' => [
        'attendance_open' => 'offen',
        'comment_missing' => 'fehlt',
        'billable' => 'abrechenbar',
    ],

    'hint' => [
        'no_attendance' => 'Keine Stempelungen an diesem Tag.',
        'attendance_correction_only' => 'Stempel sind nur über einen Korrektur-Antrag änderbar.',
        'attendance_locked' => 'Anwesenheits-Stempel sind nach der Korrektur-Freigabe gesperrt — bis zum erneuten Abschluss sind nur Buchungen änderbar.',
        'no_entries' => 'Noch keine Buchungen an diesem Tag.',
        'break_recorded' => 'Pause: :min min',
        'no_issues' => 'Keine Auffälligkeiten — der Tag ist konsistent erfasst.',
        'month_locked' => 'Dieser Tag gehört zu einem freigegebenen Monat und ist gesperrt — Abschluss und Korrekturanträge laufen über die Monatsfreigabe.',
        'correction_intro' => 'Beschreiben Sie, was an diesem Tag korrigiert werden soll.',
        'reopen_intro' => 'Der Tag wird ohne Korrekturantrag wieder geöffnet — die Begründung wird im Audit-Protokoll gespeichert.',
    ],

    // Die 7 Konsistenzprüfungen aus §4 — Schlüssel = Check-Code
    // (DayClosureValidator), Punkte im Code werden als Verschachtelung abgebildet.
    'check' => [
        'attendance' => [
            'missing_close' => 'Die Stempeluhr ist noch offen — bitte ausstempeln.',
        ],
        'time' => [
            'unallocated_minutes' => ':minutes Minuten Anwesenheit sind noch keiner Buchung zugeordnet.',
            'gap_in_attendance' => 'Anwesenheits-Lücke von :minutes Minuten ohne Pausen-Marker.',
        ],
        'break' => [
            'required' => 'Pflichtpause unterschritten: :taken von :required Minuten erfasst.',
        ],
        'balance' => [
            'threshold' => 'Tages-Saldo von :hours Stunden überschreitet ±2 h.',
        ],
        'entry' => [
            'missing_comment' => ':count abrechnungsrelevante Buchung(en) ohne Kommentar.',
        ],
        'worktime' => [
            'overrun' => 'Netto-Arbeitszeit über 10 Stunden (:minutes Minuten, ArbZG).',
        ],
        'unknown' => 'Unbekannte Prüfung: :code',
    ],

    'flash' => [
        'saved' => 'Tag :day wurde gespeichert.',
        'closed' => 'Tag :day wurde abgeschlossen.',
        'correction_requested' => 'Korrektur für :day wurde beantragt.',
        'correction_approved' => 'Korrektur für :day wurde freigegeben.',
        'correction_rejected' => 'Korrektur für :day wurde abgelehnt.',
        'reopened' => 'Tag :day wurde wieder geöffnet.',
    ],

    'errors' => [
        'month_entry_locked' => 'Der Monat ist freigegeben — für nachträgliche Zeiten bitte einen Zeitkorrektur-Antrag stellen.',
        'future_day' => 'Ein zukünftiger Tag kann nicht abgeschlossen werden.',
        'blocking_warnings' => 'Der Tag hat blockierende Warnungen und kann nicht abgeschlossen werden.',
        'illegal_day_status' => 'Aktion nicht erlaubt: Tagesstatus ist :status.',
        'illegal_request_status' => 'Aktion nicht erlaubt: Antragsstatus ist :status.',
        'reason_too_short' => 'Eine Begründung von mindestens :n Zeichen ist erforderlich.',
        'month_locked' => 'Der Monat ist bereits freigegeben oder gesperrt — bitte zuerst die Monatsfreigabe öffnen.',
        'owner_missing' => 'Tagesabschluss ohne gültigen Eigentümer.',
        'closure_missing' => 'Korrekturantrag ohne zugehörigen Tagesabschluss.',
        // Tooltip-Begründungen für den deaktivierten Abschließen-Button (§2.6).
        'close_blocked' => [
            'future' => 'Ein zukünftiger Tag kann nicht abgeschlossen werden.',
            'month_locked' => 'Der Monat ist bereits freigegeben oder gesperrt.',
            'blocking' => 'Blockierende Warnungen (⛔) müssen zuerst behoben werden.',
            'not_open' => 'Der Tag ist nicht offen.',
        ],
    ],

    'modal' => [
        'correction_title' => 'Korrektur anfordern',
        'reopen_title' => 'Tag wieder öffnen',
    ],
];
