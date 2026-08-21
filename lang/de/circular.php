<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : circular.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

return [
    'title' => 'Rundschreiben',
    'subtitle' => 'Geschäftsmitteilungen an einen gefilterten Kundenkreis',
    'empty' => 'Noch kein Rundschreiben angelegt.',
    'empty_recipients' => 'Keine Empfänger erfasst.',
    'created' => 'Rundschreiben angelegt.',
    'sent' => 'Rundschreiben versendet.',
    'already_sent' => 'Dieses Rundschreiben wurde bereits versendet.',
    'no_recipients' => 'Der gewählte Filter trifft auf keinen Kunden zu.',
    'mandatory_short' => 'Pflichtmitteilung',
    'portal_short' => 'Im Portal sichtbar',
    'no_email' => 'keine E-Mail-Adresse',
    'confirm_send' => 'Rundschreiben jetzt an :count Empfänger versenden?',
    'body_hint' => 'Platzhalter: :firma, :kunde, :ansprechpartner',
    'mandatory_hint' => 'Pflichtmitteilungen gehen auch an Kunden, die Sammelmails abbestellt haben — nur für rechtlich gebotene Informationen.',
    'portal_hint' => 'Die Mitteilung erscheint zusätzlich im Kundenportal.',

    'audience' => [
        'heading' => 'Empfänger (:count)',
    ],

    'action' => [
        'create' => 'Rundschreiben anlegen',
        'save_draft' => 'Als Entwurf speichern',
        'send' => 'Versenden',
        'show' => 'Ansehen',
    ],

    'column' => [
        'subject' => 'Betreff',
        'status' => 'Status',
        'recipients' => 'Empfänger',
        'skipped' => 'Nicht erreicht',
        'sent_at' => 'Versendet am',
        'customer' => 'Kunde',
        'email' => 'E-Mail',
    ],

    'field' => [
        'body' => 'Text',
        'is_mandatory' => 'Pflichtmitteilung',
        'portal_notice' => 'Im Kundenportal anzeigen',
    ],

    'filter' => [
        'search' => 'Suche',
        'city' => 'Ort',
        'zip_prefix' => 'PLZ beginnt mit',
        'zip_hint' => 'z. B. 30 für den Raum Hannover',
        'with_active_projects' => 'nur Kunden mit aktivem Projekt',
    ],

    'status' => [
        'draft' => 'Entwurf',
        'sending' => 'wird versendet',
        'sent' => 'versendet',
    ],

    'recipient_status' => [
        'pending' => 'offen',
        'sent' => 'zugestellt',
        'skipped' => 'übersprungen',
        'failed' => 'fehlgeschlagen',
    ],

    'reason' => [
        'no_email' => 'keine E-Mail-Adresse hinterlegt',
    ],
];
