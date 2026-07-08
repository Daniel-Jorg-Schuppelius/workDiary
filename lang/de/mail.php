<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : mail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'E-Mail-Eingang',
    'intro' => 'Verbundene IMAP-Postfächer werden über den Scheduler abgerufen; neue Mails landen als Vorschläge in der Integrations-Inbox und werden dem Kunden zugeordnet — nie blind angelegt. Verarbeitete Mails werden nur markiert/verschoben, nie gelöscht. WorkDiary ist kein Mail-Client.',
    'to_inbox' => 'Zur Zuordnungs-Inbox',

    'mailboxes_heading' => 'Postfächer',
    'no_connections' => 'Noch kein Postfach verbunden.',
    'add_heading' => 'Postfach hinzufügen',

    'inbox' => [
        'no_subject' => '(ohne Betreff)',
        'book_action' => 'Als Kommunikationsnotiz buchen',
        'book_customer_placeholder' => '… Kunde (leer = erkannter Absender)',
    ],

    'dms' => [
        'action' => 'In die Dokumentenablage übernehmen',
        'origin' => 'Aus E-Mail übernommen: :subject (Message-ID :message_id)',
        'imported' => ':count Anhang/Anhänge in die Dokumentenablage übernommen.',
        'none' => 'Keine übernehmbaren Anhänge vorhanden.',
    ],

    'encryption' => [
        'none' => 'Keine',
    ],

    'field' => [
        'name' => 'Bezeichnung',
        'host' => 'IMAP-Server',
        'port' => 'Port',
        'encryption' => 'Verschlüsselung',
        'username' => 'Benutzername',
        'password' => 'Passwort',
        'folder' => 'Ordner',
        'processed_folder' => 'Zielordner (verarbeitet)',
        'processed_folder_placeholder' => 'optional, z. B. Verarbeitet',
        'active' => 'Aktiv',
    ],

    'action' => [
        'poll' => 'Jetzt abrufen',
        'disconnect' => 'Trennen',
        'save' => 'Speichern',
    ],

    'col' => [
        'host' => 'Konto',
        'status' => 'Status',
        'last_polled' => 'Zuletzt abgerufen',
    ],

    'status' => [
        'active' => 'Aktiv',
        'inactive' => 'Inaktiv',
    ],

    'flash' => [
        'saved' => 'Postfach gespeichert.',
        'disconnected' => 'Postfach getrennt.',
        'polled' => 'Abruf gestartet.',
        'booked' => 'Mail als Kommunikationseintrag übernommen.',
        'book_failed' => 'Übernahme fehlgeschlagen.',
        'password_required' => 'Für ein neues Postfach ist ein Passwort erforderlich.',
        'customer_required' => 'Kein Kunde zugeordnet.',
    ],
    'reference' => [
        'customer_number' => 'Kundennummer im Text: :number',
        'invoice_number' => 'Rechnungsnummer im Text: :number',
        'project_number' => 'Projektnummer im Text: :number',
    ],
];
