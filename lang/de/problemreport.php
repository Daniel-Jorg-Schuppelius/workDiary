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
        'create' => 'Problem melden',
        'eyebrow' => 'Technisches Problem',
        'index' => 'Meine Fehlermeldungen',
        'index_subtitle' => 'Ihre gemeldeten technischen Probleme mit Referenznummer und Status.',
        'inbox' => 'Fehlermeldungen',
        'inbox_subtitle' => 'Eingegangene technische Problemmeldungen — prüfen, beantworten, als Ticket übernehmen.',
    ],
    'section' => [
        'what' => 'Was ist passiert?',
        'context' => 'Übertragene Angaben',
    ],
    'field' => [
        'summary' => 'Kurzbeschreibung',
        'description' => 'Beschreibung',
        'expected' => 'Erwartetes Verhalten',
        'actual' => 'Tatsächliches Verhalten',
        'severity' => 'Schweregrad',
        'screenshots' => 'Screenshots/Anhänge (max. 3)',
        'contact_ok' => 'Der Support darf mich zu dieser Meldung kontaktieren.',
        'contact_ok_short' => 'Rückkontakt ok',
        'include_diagnostics' => 'Redaktierten Diagnoseauszug mitsenden (empfohlen)',
        'reference' => 'Referenz',
        'status' => 'Status',
        'created_at' => 'Gemeldet am',
        'reporter' => 'Melder',
        'diagnostics' => 'Diagnoseauszug (redaktiert)',
        'delivery_error' => 'Zustellfehler',
        'ticket' => 'Ticket',
    ],
    'severity' => [
        'low' => 'Gering',
        'normal' => 'Normal',
        'high' => 'Hoch',
        'blocking' => 'Blockierend',
    ],
    'status' => [
        'new' => 'Neu',
        'in_review' => 'In Prüfung',
        'answered' => 'Beantwortet',
        'closed' => 'Geschlossen',
    ],
    'delivery' => [
        'saas_inbox' => 'Support-Inbox (dieses System)',
        'mail' => 'Support-E-Mail',
        'webhook' => 'Webhook',
        'local_export' => 'Lokaler Export',
    ],
    'action' => [
        'submit' => 'Meldung absenden',
        'open' => 'Öffnen',
        'set_status' => 'Status setzen',
        'download' => 'Als JSON herunterladen',
        'convert' => 'Als Ticket übernehmen',
    ],
    'hint' => [
        'context' => 'Diese technischen Angaben werden mit Ihrer Meldung übertragen — keine Auftrags- oder Kundendaten.',
        'diagnostics_always' => 'Gemäß Organisationsregel wird ein redaktierter Diagnoseauszug mitgesendet.',
        'diagnostics_preview' => 'Diagnoseauszug ansehen (wird genau so übertragen)',
        'no_diagnostics' => 'Kein Diagnoseauszug angehängt (Melder-Entscheidung bzw. Organisationsregel).',
    ],
    'context' => [
        'route' => 'Seite',
        'topic' => 'Hilfe-Thema',
        'version' => 'App-Version',
    ],
    'empty' => [
        'title' => 'Keine Meldungen',
        'message' => 'Sie haben noch kein technisches Problem gemeldet.',
        'inbox_title' => 'Keine Fehlermeldungen',
        'inbox_message' => 'Aktuell liegen keine technischen Problemmeldungen vor.',
    ],
    'filter' => [
        'all_statuses' => 'Alle Status',
    ],
    'flash' => [
        'created' => 'Danke! Ihre Meldung wurde unter :reference erfasst.',
        'status_updated' => 'Status aktualisiert.',
        'converted' => 'Als Ticket :reference übernommen.',
        'already_converted' => 'Bereits als Ticket :reference übernommen.',
    ],
    'mail' => [
        'heading' => 'Fehlermeldung :reference',
        'contact_ok' => ':name ist mit Rückfragen einverstanden.',
        'attachment_hint' => 'Der vollständige redaktierte Datensatz liegt als JSON-Anhang bei.',
    ],
];
