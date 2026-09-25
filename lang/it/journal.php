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
        'analyzed' => 'Analizzato',
        'blocked' => 'Bloccato',
        'cancelled' => 'Annullato',
        'completed' => 'Completato',
        'cutover_executed' => 'Passaggio eseguito',
        'item_decided' => 'Voce decisa',
        'parallel_run_started' => 'Esercizio parallelo avviato',
        'planned' => 'Pianificato',
        'status_changed' => 'Stato modificato',
        'position_edited' => 'Posizione modificata',
        'position_removed' => 'Posizione rimossa',
        'positions_merged' => 'Posizioni unite',
        'texts_edited' => 'Testi modificati',
        'discarded' => 'Scartato',
        'finalized' => 'Consolidato',
        'sources_removed' => 'Fonti rimosse',
        'return_processed' => 'Reso elaborato',
        'skonto_accepted' => 'Sconto cassa accettato',
        'unmatched' => 'Abbinamento annullato',
        'accounting' => [
            'opening_balance_imported' => 'Bilancio di apertura importato',
        ],
    ],
    'privacy' => [
        'assessed' => 'Valutato',
        'authority_report_recorded' => 'Notifica all\'autorità registrata',
        'closed' => 'Chiuso',
        'controller_notified' => 'Titolare informato',
        'measure_added' => 'Misura aggiunta',
        'measure_completed' => 'Misura completata',
        'notification_decided' => 'Obbligo di notifica deciso',
        'opened' => 'Aperto',
        'reported' => 'Segnalato',
        'deadline_reminder' => 'Promemoria scadenza',
        'assigned' => 'Assegnato',
        'decided' => 'Deciso',
        'identity_verified' => 'Identità verificata',
        'portal_email_confirmed' => 'E-mail del portale confermata',
        'portal_receipt_failed' => 'Conferma di ricezione del portale non riuscita',
        'portal_receipt_sent' => 'Conferma di ricezione del portale inviata',
        'portal_submitted' => 'Inviato tramite il portale',
        'shredded' => 'Distrutto',
        'subject_export_generated' => 'Esportazione dell\'interessato generata',
    ],
    'whistleblowing' => [
        'assigned' => 'Assegnato',
        'deadline_reminder' => 'Promemoria scadenza',
        'decided' => 'Deciso',
        'identity_verified' => 'Identità verificata',
        'opened' => 'Aperto',
        'portal_email_confirmed' => 'E-mail del portale confermata',
        'portal_receipt_failed' => 'Conferma di ricezione del portale non riuscita',
        'portal_receipt_sent' => 'Conferma di ricezione del portale inviata',
        'portal_submitted' => 'Inviato tramite il portale',
        'shredded' => 'Distrutto',
        'subject_export_generated' => 'Esportazione generata',
    ],
    'agile' => [
        'backlog' => [
            'added' => 'Aggiunto al backlog',
            'removed' => 'Rimosso dal backlog',
            'reranked' => 'Backlog riordinato',
        ],
        'column' => [
            'moved' => 'Colonna cambiata',
        ],
        'sprint' => [
            'item_added' => 'Aggiunto allo sprint',
            'item_removed' => 'Rimosso dallo sprint',
            'started' => 'Sprint avviato',
            'completed' => 'Sprint completato',
            'cancelled' => 'Sprint annullato',
        ],
        'item' => [
            'blocked' => 'Bloccato',
            'unblocked' => 'Sbloccato',
        ],
        'points' => [
            'changed' => 'Story point modificati',
        ],
        'override' => [
            'wip' => 'Limite WIP forzato',
            'dod' => 'Definition of Done forzata',
            'criteria' => 'Criteri di accettazione forzati',
        ],
        'epic' => [
            'assigned' => 'Epica assegnata',
        ],
    ],
    'diary' => [
        'order' => [
            'created' => 'Ordine creato',
            'accept' => 'Ordine accettato',
            'start' => 'Ordine avviato',
            'pause' => 'Ordine sospeso',
            'resume' => 'Ordine ripreso',
            'complete' => 'Ordine completato',
            'acceptance' => 'Collaudo avviato',
            'invoice' => 'Fattura generata',
            'cancel' => 'Ordine annullato',
        ],
        'dispatch' => [
            'gap_fill_applied' => 'Proposta di tempo libero applicata',
            'gap_fill_dismissed' => 'Proposta di tempo libero scartata',
            'calendly_confirmed' => 'Richiesta Calendly confermata',
        ],
        'issue' => [
            'created' => 'Punto aperto creato',
            'assigned' => 'Assegnato',
            'started' => 'Avviato',
            'blocked' => 'Bloccato',
            'unblocked' => 'Sbloccato',
            'completed' => 'Completato',
            'wontDo' => 'Non verrà eseguito',
            'reopened' => 'Riaperto',
            'dueDateChanged' => 'Scadenza modificata',
            'severityChanged' => 'Gravità modificata',
            'visibilityChanged' => 'Visibilità modificata',
            'commentAdded' => 'Commento aggiunto',
            'attachmentAdded' => 'Allegato aggiunto',
        ],
    ],
    'time' => [
        'month' => [
            'approved' => 'Mese approvato',
            'locked' => 'Mese bloccato',
            'rejected' => 'Mese respinto',
            'submitted' => 'Mese inviato',
        ],
        'export' => [
            'delivered' => 'Consegnato',
            'downloaded' => 'Scaricato',
            'line_updated' => 'Riga aggiornata',
            'preparing' => 'In preparazione',
            'ready' => 'Pronto',
            'rejected' => 'Respinto',
            'superseded' => 'Sostituito',
            'delivered_auto' => 'Consegnato automaticamente',
            'delivery_failed' => 'Consegna non riuscita',
        ],
    ],
    'procedure' => [
        'procedure' => [
            'runStarted' => 'Esecuzione avviata',
            'stepCompleted' => 'Passo completato',
            'stepFailed' => 'Passo non riuscito',
            'stepDeviated' => 'Passo con deviazione',
            'stepNA' => 'Passo non applicabile',
            'stepUnlocked' => 'Passo sbloccato',
            'stepBlocked' => 'Passo bloccato',
            'runCompleted' => 'Esecuzione completata',
            'runCompletionRejected' => 'Chiusura respinta',
            'runAborted' => 'Esecuzione interrotta',
            'runBlocked' => 'Esecuzione bloccata',
            'runUnblocked' => 'Esecuzione sbloccata',
            'secondPersonAssigned' => 'Seconda persona assegnata',
            'secondPersonSigned' => 'Seconda persona ha firmato',
            'secondPersonRequested' => 'Seconda persona richiesta',
            'secondPersonRevoked' => 'Seconda persona revocata',
            'backupRegistered' => 'Backup registrato',
            'backupVerified' => 'Backup verificato',
            'backupRejected' => 'Backup respinto',
            'deviationRecorded' => 'Deviazione registrata',
            'deviationUpdated' => 'Deviazione aggiornata',
            'deviationActionTriggered' => 'Azione per deviazione attivata',
            'criticalRiskAccepted' => 'Rischio critico accettato',
        ],
    ],
    'protocol' => [
        'protocol' => [
            'created' => 'Verbale creato',
            'itemAdded' => 'Voce aggiunta',
            'itemRemoved' => 'Voce rimossa',
            'itemReordered' => 'Voci riordinate',
            'itemFilled' => 'Voce compilata',
            'requestedReview' => 'Revisione richiesta',
            'returnedToDraft' => 'Riportato in bozza',
            'signed' => 'Firmato',
            'archived' => 'Archiviato',
            'supersededBy' => 'Sostituito da una versione successiva',
            'attachmentAdded' => 'Allegato aggiunto',
            'attachmentRemoved' => 'Allegato rimosso',
            'signatureRequested' => 'Firma richiesta',
            'signatureLinkOpened' => 'Link di firma aperto',
            'signatureRejected' => 'Firma respinta',
            'signatureLinkRevoked' => 'Link di firma revocato',
            'customerQueryRaised' => 'Richiesta del cliente posta',
            'customerQueryAnswered' => 'Richiesta del cliente evasa',
            'pdfRendered' => 'PDF generato',
            'pdfDownloaded' => 'PDF scaricato',
            'item' => [
                'photoAdded' => 'Foto aggiunta',
                'photoRemoved' => 'Foto rimossa',
                'photoReordered' => 'Foto riordinate',
                'photoUpdatedCaption' => 'Didascalia modificata',
            ],
        ],
    ],
    'learning' => [
        'status_changed' => 'Stato modificato',
    ],
    'auth' => [
        'auth' => [
            'lockout' => 'Account bloccato',
            '2fa_failed' => 'Secondo fattore non riuscito',
            'password_reset_requested' => 'Reimpostazione password richiesta',
            'impossible_travel' => 'Spostamento impossibile rilevato',
        ],
        'wb' => [
            'login_failed' => 'Canale di segnalazione: accesso non riuscito',
        ],
        'api' => [
            'token_invalid' => 'Token API non valido',
        ],
        'webhook' => [
            'signature_invalid' => 'Firma webhook non valida',
        ],
        'sso' => [
            'failed' => 'SSO non riuscito',
        ],
        'terminal' => [
            'badge_unknown' => 'Badge terminale sconosciuto',
        ],
        'admin' => [
            'ip_blocked' => 'IP amministratore piattaforma bloccato',
        ],
    ],
];
