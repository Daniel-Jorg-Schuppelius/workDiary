<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : journal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Journal-Ereignisse (MVP-864): `journal.<modulcode>.<event>`, gelesen von
// JournalEntry::label(); Ereignisse mit Punkten liegen verschachtelt
// (`order.created` → ['order' => ['created' => …]]).
return [
    'finance' => [
        'analyzed' => 'Analysiert',
        'blocked' => 'Blockiert',
        'cancelled' => 'Abgebrochen',
        'completed' => 'Abgeschlossen',
        'cutover_executed' => 'Umstellung durchgeführt',
        'item_decided' => 'Position entschieden',
        'parallel_run_started' => 'Parallelbetrieb gestartet',
        'planned' => 'Geplant',
        'status_changed' => 'Status geändert',
        'position_edited' => 'Position bearbeitet',
        'position_removed' => 'Position entfernt',
        'positions_merged' => 'Positionen zusammengefasst',
        'texts_edited' => 'Texte bearbeitet',
        'discarded' => 'Verworfen',
        'finalized' => 'Festgeschrieben',
        'sources_removed' => 'Quellen entfernt',
        'return_processed' => 'Rückläufer verarbeitet',
        'skonto_accepted' => 'Skonto akzeptiert',
        'unmatched' => 'Zuordnung aufgehoben',
        'accounting' => [
            'opening_balance_imported' => 'Eröffnungsbilanz importiert',
        ],
    ],
    'privacy' => [
        'assessed' => 'Bewertet',
        'authority_report_recorded' => 'Behördenmeldung erfasst',
        'closed' => 'Geschlossen',
        'controller_notified' => 'Verantwortlicher informiert',
        'measure_added' => 'Maßnahme hinzugefügt',
        'measure_completed' => 'Maßnahme abgeschlossen',
        'notification_decided' => 'Meldepflicht entschieden',
        'opened' => 'Eröffnet',
        'reported' => 'Gemeldet',
        'deadline_reminder' => 'Fristerinnerung',
        'assigned' => 'Zugewiesen',
        'decided' => 'Entschieden',
        'identity_verified' => 'Identität geprüft',
        'portal_email_confirmed' => 'Portal-E-Mail bestätigt',
        'portal_receipt_failed' => 'Portal-Eingangsbestätigung fehlgeschlagen',
        'portal_receipt_sent' => 'Portal-Eingangsbestätigung gesendet',
        'portal_submitted' => 'Über das Portal eingereicht',
        'shredded' => 'Vernichtet',
        'subject_export_generated' => 'Betroffenenauskunft erzeugt',
    ],
    'whistleblowing' => [
        'assigned' => 'Zugewiesen',
        'deadline_reminder' => 'Fristerinnerung',
        'decided' => 'Entschieden',
        'identity_verified' => 'Identität geprüft',
        'opened' => 'Eröffnet',
        'portal_email_confirmed' => 'Portal-E-Mail bestätigt',
        'portal_receipt_failed' => 'Portal-Eingangsbestätigung fehlgeschlagen',
        'portal_receipt_sent' => 'Portal-Eingangsbestätigung gesendet',
        'portal_submitted' => 'Über das Portal eingereicht',
        'shredded' => 'Vernichtet',
        'subject_export_generated' => 'Auskunft erzeugt',
    ],
    'agile' => [
        'backlog' => [
            'added' => 'Ins Backlog aufgenommen',
            'removed' => 'Aus dem Backlog entfernt',
            'reranked' => 'Backlog neu sortiert',
        ],
        'column' => [
            'moved' => 'Spalte gewechselt',
        ],
        'sprint' => [
            'item_added' => 'In den Sprint aufgenommen',
            'item_removed' => 'Aus dem Sprint entfernt',
            'started' => 'Sprint gestartet',
            'completed' => 'Sprint abgeschlossen',
            'cancelled' => 'Sprint abgebrochen',
        ],
        'item' => [
            'blocked' => 'Blockiert',
            'unblocked' => 'Blockade aufgehoben',
        ],
        'points' => [
            'changed' => 'Story Points geändert',
        ],
        'override' => [
            'wip' => 'WIP-Limit übersteuert',
            'dod' => 'Definition of Done übersteuert',
            'criteria' => 'Akzeptanzkriterien übersteuert',
        ],
        'epic' => [
            'assigned' => 'Epic zugeordnet',
        ],
    ],
    'diary' => [
        'order' => [
            'created' => 'Auftrag angelegt',
            'accept' => 'Auftrag angenommen',
            'start' => 'Auftrag begonnen',
            'pause' => 'Auftrag pausiert',
            'resume' => 'Auftrag fortgesetzt',
            'complete' => 'Auftrag abgeschlossen',
            'acceptance' => 'Abnahme gestartet',
            'invoice' => 'Rechnung ausgelöst',
            'cancel' => 'Auftrag storniert',
        ],
        'dispatch' => [
            'gap_fill_applied' => 'Leerzeit-Vorschlag übernommen',
            'gap_fill_dismissed' => 'Leerzeit-Vorschlag verworfen',
            'calendly_confirmed' => 'Calendly-Terminwunsch bestätigt',
        ],
        'issue' => [
            'created' => 'Offener Punkt angelegt',
            'assigned' => 'Zugewiesen',
            'started' => 'Begonnen',
            'blocked' => 'Blockiert',
            'unblocked' => 'Blockade aufgehoben',
            'completed' => 'Erledigt',
            'wontDo' => 'Wird nicht umgesetzt',
            'reopened' => 'Wieder geöffnet',
            'dueDateChanged' => 'Fälligkeit geändert',
            'severityChanged' => 'Schwere geändert',
            'visibilityChanged' => 'Sichtbarkeit geändert',
            'commentAdded' => 'Kommentar hinzugefügt',
            'attachmentAdded' => 'Anhang hinzugefügt',
        ],
    ],
    'time' => [
        'month' => [
            'approved' => 'Monat freigegeben',
            'locked' => 'Monat gesperrt',
            'rejected' => 'Monat zurückgewiesen',
            'submitted' => 'Monat eingereicht',
        ],
        'export' => [
            'delivered' => 'Zugestellt',
            'downloaded' => 'Heruntergeladen',
            'line_updated' => 'Zeile aktualisiert',
            'preparing' => 'Wird vorbereitet',
            'ready' => 'Bereit',
            'rejected' => 'Zurückgewiesen',
            'superseded' => 'Ersetzt',
            'delivered_auto' => 'Automatisch zugestellt',
            'delivery_failed' => 'Zustellung fehlgeschlagen',
        ],
    ],
    'procedure' => [
        'procedure' => [
            'runStarted' => 'Lauf gestartet',
            'stepCompleted' => 'Schritt erledigt',
            'stepFailed' => 'Schritt fehlgeschlagen',
            'stepDeviated' => 'Schritt abgewichen',
            'stepNA' => 'Schritt nicht zutreffend',
            'stepUnlocked' => 'Schritt entsperrt',
            'stepBlocked' => 'Schritt blockiert',
            'runCompleted' => 'Lauf abgeschlossen',
            'runCompletionRejected' => 'Abschluss abgelehnt',
            'runAborted' => 'Lauf abgebrochen',
            'secondPersonAssigned' => 'Zweitperson zugewiesen',
            'secondPersonSigned' => 'Zweitperson hat gezeichnet',
            'secondPersonRequested' => 'Zweitperson angefordert',
            'secondPersonRevoked' => 'Zweitperson zurückgezogen',
            'backupRegistered' => 'Backup registriert',
            'backupVerified' => 'Backup verifiziert',
            'backupRejected' => 'Backup abgelehnt',
            'deviationRecorded' => 'Abweichung erfasst',
            'deviationUpdated' => 'Abweichung aktualisiert',
            'deviationActionTriggered' => 'Abweichungsmaßnahme ausgelöst',
            'criticalRiskAccepted' => 'Kritisches Risiko akzeptiert',
        ],
    ],
    'protocol' => [
        'protocol' => [
            'created' => 'Protokoll angelegt',
            'itemAdded' => 'Position hinzugefügt',
            'itemRemoved' => 'Position entfernt',
            'itemReordered' => 'Positionen umsortiert',
            'itemFilled' => 'Position ausgefüllt',
            'requestedReview' => 'Prüfung angefordert',
            'returnedToDraft' => 'Auf Entwurf zurückgesetzt',
            'signed' => 'Unterschrieben',
            'archived' => 'Archiviert',
            'supersededBy' => 'Ersetzt durch Folgeversion',
            'attachmentAdded' => 'Anhang hinzugefügt',
            'attachmentRemoved' => 'Anhang entfernt',
            'signatureRequested' => 'Unterschrift angefordert',
            'signatureLinkOpened' => 'Unterschriftslink geöffnet',
            'signatureRejected' => 'Unterschrift abgelehnt',
            'signatureLinkRevoked' => 'Unterschriftslink widerrufen',
            'customerQueryRaised' => 'Kundenrückfrage gestellt',
            'customerQueryAnswered' => 'Kundenrückfrage beantwortet',
            'pdfRendered' => 'PDF erzeugt',
            'pdfDownloaded' => 'PDF heruntergeladen',
            'item' => [
                'photoAdded' => 'Foto hinzugefügt',
                'photoRemoved' => 'Foto entfernt',
                'photoReordered' => 'Fotos umsortiert',
                'photoUpdatedCaption' => 'Bildunterschrift geändert',
            ],
        ],
    ],
    'learning' => [
        'status_changed' => 'Status geändert',
    ],
    'auth' => [
        'auth' => [
            'lockout' => 'Konto gesperrt',
            '2fa_failed' => 'Zweiter Faktor fehlgeschlagen',
            'password_reset_requested' => 'Passwort-Zurücksetzung angefordert',
            'impossible_travel' => 'Unmögliche Reise erkannt',
        ],
        'wb' => [
            'login_failed' => 'Meldestelle: Anmeldung fehlgeschlagen',
        ],
        'api' => [
            'token_invalid' => 'API-Token ungültig',
        ],
        'webhook' => [
            'signature_invalid' => 'Webhook-Signatur ungültig',
        ],
        'sso' => [
            'failed' => 'SSO fehlgeschlagen',
        ],
        'terminal' => [
            'badge_unknown' => 'Unbekanntes Terminal-Badge',
        ],
        'admin' => [
            'ip_blocked' => 'Plattform-Admin-IP blockiert',
        ],
    ],
];
