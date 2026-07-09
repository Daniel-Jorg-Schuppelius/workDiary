<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : problemreport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'create' => 'Segnala un problema',
        'eyebrow' => 'Problema tecnico',
        'index' => 'Le mie segnalazioni',
        'index_subtitle' => 'I problemi tecnici segnalati con numero di riferimento e stato.',
        'inbox' => 'Segnalazioni di problemi',
        'inbox_subtitle' => 'Segnalazioni tecniche in arrivo — esaminare, rispondere, convertire in ticket.',
    ],
    'section' => [
        'what' => 'Cosa è successo?',
        'context' => 'Dati trasmessi',
    ],
    'field' => [
        'summary' => 'Riepilogo',
        'description' => 'Descrizione',
        'expected' => 'Comportamento atteso',
        'actual' => 'Comportamento osservato',
        'severity' => 'Gravità',
        'screenshots' => 'Screenshot/allegati (max. 3)',
        'contact_ok' => 'Il supporto può contattarmi in merito a questa segnalazione.',
        'contact_ok_short' => 'Contatto ok',
        'include_diagnostics' => 'Includi estratto diagnostico anonimizzato (consigliato)',
        'reference' => 'Riferimento',
        'status' => 'Stato',
        'created_at' => 'Segnalato il',
        'reporter' => 'Segnalante',
        'diagnostics' => 'Estratto diagnostico (anonimizzato)',
        'delivery_error' => 'Errore di invio',
        'ticket' => 'Ticket',
    ],
    'severity' => [
        'low' => 'Bassa',
        'normal' => 'Normale',
        'high' => 'Alta',
        'blocking' => 'Bloccante',
    ],
    'status' => [
        'new' => 'Nuova',
        'in_review' => 'In esame',
        'answered' => 'Risposta inviata',
        'closed' => 'Chiusa',
    ],
    'delivery' => [
        'saas_inbox' => 'Inbox di supporto (questo sistema)',
        'mail' => 'E-mail di supporto',
        'webhook' => 'Webhook',
        'local_export' => 'Esportazione locale',
    ],
    'action' => [
        'submit' => 'Invia segnalazione',
        'open' => 'Apri',
        'set_status' => 'Imposta stato',
        'download' => 'Scarica come JSON',
        'convert' => 'Converti in ticket',
    ],
    'hint' => [
        'context' => 'Questi dati tecnici vengono trasmessi con la segnalazione — nessun dato di clienti o commesse.',
        'diagnostics_always' => 'Secondo la regola dell\'organizzazione viene incluso un estratto diagnostico anonimizzato.',
        'diagnostics_preview' => 'Visualizza estratto diagnostico (trasmesso esattamente così)',
        'no_diagnostics' => 'Nessun estratto diagnostico allegato (scelta del segnalante o regola dell\'organizzazione).',
    ],
    'context' => [
        'route' => 'Pagina',
        'topic' => 'Argomento della guida',
        'version' => 'Versione app',
    ],
    'empty' => [
        'title' => 'Nessuna segnalazione',
        'message' => 'Non hai ancora segnalato alcun problema tecnico.',
        'inbox_title' => 'Nessuna segnalazione',
        'inbox_message' => 'Al momento non ci sono segnalazioni tecniche.',
    ],
    'filter' => [
        'all_statuses' => 'Tutti gli stati',
    ],
    'flash' => [
        'created' => 'Grazie! La segnalazione è stata registrata come :reference.',
        'status_updated' => 'Stato aggiornato.',
        'converted' => 'Convertita nel ticket :reference.',
        'already_converted' => 'Già convertita nel ticket :reference.',
    ],
    'mail' => [
        'heading' => 'Segnalazione :reference',
        'contact_ok' => ':name acconsente a domande di follow-up.',
        'attachment_hint' => 'Il record anonimizzato completo è allegato in formato JSON.',
    ],
];
