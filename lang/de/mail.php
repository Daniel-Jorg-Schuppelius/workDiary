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
    'intro' => 'Postfächer werden regelmäßig ab dem zuletzt erreichten Stand abgerufen, auch bereits gelesene Mails. Neue Mails landen als Vorschläge in der Integrations-Inbox, Rechnungen im Rechnungseingang; verarbeitete Mails werden nur markiert oder verschoben, nie gelöscht.',
    'to_inbox' => 'Zur Zuordnungs-Inbox',
    'title_edit' => 'Postfach bearbeiten',
    'to_inbox_count' => 'Zur Zuordnungs-Inbox (:count)',

    'mailboxes_heading' => 'Postfächer',
    'no_connections' => 'Noch kein Postfach verbunden.',
    'add_heading' => 'Postfach hinzufügen',

    'inbox' => [
        'invoice_action' => 'Als Eingangsrechnung übernehmen',
        'no_subject' => '(ohne Betreff)',
        'invoice_mailbox' => 'Rechnungspostfach · :from',
        'book_action' => 'Als Kommunikationsnotiz buchen',
        'book_ticket_action' => 'Als Service-Ticket buchen',
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
        'mailbox_address' => 'Postfachadresse',
        'mailbox_address_hint' => 'Leer lassen für das Postfach des verbundenen Kontos. Für ein freigegebenes Postfach (z. B. rechnungen@…) braucht das Konto Vollzugriff darauf.',
        'password_keep' => 'Leer lassen, um das gespeicherte Passwort zu behalten.',
        'fetch_since' => 'Abruf ab',
        'fetch_since_hint' => 'Mails, die vor diesem Tag eingegangen sind, werden nicht abgerufen. Ein früheres Datum holt ältere Mails nach.',
        'folder_hint_list' => 'Vorschläge kommen aus dem Postfach; Unterordner mit „/“ trennen, z. B. Posteingang/Rechnungen.',
        'folder_hint_error' => 'Die Ordnerliste ließ sich nicht laden (:error). Ordner von Hand eintragen, Unterordner mit „/“ trennen.',
        'folder_hint_free' => 'Nach dem ersten Speichern schlägt der Dialog die Ordner des Postfachs vor. Unterordner mit „/“ trennen, z. B. INBOX/Rechnungen.',
        'einvoice_intake' => 'Rechnungspostfach: Rechnungen aus den Anhängen in den Rechnungseingang übernehmen',
        'einvoice_intake_hint' => 'Jede PDF-, XML- oder Bilddatei einer Mail wird ein Eingang. Am besten einen eigenen Ordner wählen, in den eine Regel die Rechnungen sortiert.',
        'callreport_intake' => 'Telefonbericht-Postfach: FRITZ!Box-Anruflisten (CSV) in den Anruflisten-Import übernehmen',
        'name' => 'Bezeichnung',
        'transport' => 'Transport',
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
        'create' => 'Neues Postfach',
        'edit' => 'Bearbeiten',
        'backfill' => 'Ältere Mails nachholen',
        'activate' => 'Aktivieren',
        'poll' => 'Jetzt abrufen',
        'disconnect' => 'Trennen',
        'save' => 'Speichern',
    ],

    'col' => [
        'purpose' => 'Verwendung',
        'fetch_since' => 'ab :date',
        'host' => 'Konto',
        'status' => 'Status',
        'last_polled' => 'Zuletzt abgerufen',
    ],

    'status' => [
        'error' => 'Fehler',
        'backfill_running' => 'Nachholen läuft · :count verarbeitet',
        'active' => 'Aktiv',
        'inactive' => 'Inaktiv',
    ],

    'flash' => [
        'invoice_imported' => 'In den Rechnungseingang übernommen: :count.',
        'invoice_duplicate' => 'Diese Rechnung liegt schon im Rechnungseingang.',
        'invoice_none' => 'Unter den Anhängen ist keine PDF-, XML- oder Bilddatei.',
        'invoice_failed' => 'Übernahme in den Rechnungseingang fehlgeschlagen.',
        'backfill_started' => 'Nachholen gestartet. Die nächsten Abrufe arbeiten die älteren Mails in Stapeln ab.',
        'activated' => 'Postfach aktiviert.',
        'chained_folders' => 'Hinweis: Ein Ordner dieses Postfachs ist zugleich Abruf- bzw. Verarbeitet-Ordner von „:name“. Mails würden zwischen den Einträgen weitergereicht.',
        'saved' => 'Postfach gespeichert.',
        'disconnected' => 'Postfach getrennt.',
        'polled' => 'Abruf gestartet.',
        'booked' => 'Mail als Kommunikationseintrag übernommen.',
        'book_failed' => 'Übernahme fehlgeschlagen.',
        'ticket_booked' => 'Mail als Service-Ticket übernommen.',
        'ticket_failed' => 'Ticket-Übernahme fehlgeschlagen.',
        'dms_failed' => 'DMS-Übernahme fehlgeschlagen.',
        'already_resolved' => 'Der Eintrag ist bereits aufgelöst.',
        'password_required' => 'Für ein neues Postfach ist ein Passwort erforderlich.',
        'customer_required' => 'Kein Kunde zugeordnet.',
    ],
    'transport' => [
        'imap' => 'IMAP-Postfach',
    ],
    'access' => [
        'own_mailbox' => 'Postfach des verbundenen Kontos',
    ],
    'group' => [
        'mailbox' => 'Postfach',
        'folders' => 'Ordner und Startpunkt',
        'purpose' => 'Verwendung',
    ],
    'purpose' => [
        'invoices' => 'Rechnungen',
        'callreports' => 'Anruflisten',
        'inbox' => 'Inbox',
    ],
    'validation' => [
        'duplicate_folder' => 'Für diesen Zugang gibt es schon einen Eintrag mit diesem Ordner.',
    ],
    'backfill' => [
        'title' => 'Ältere Mails nachholen',
        'submit' => 'Nachholen',
        'intro' => 'Die nächsten Abrufe verarbeiten alle Mails im Ordner „:folder“ ab dem gewählten Tag, auch bereits gelesene.',
        'note_folder' => 'Nachgeholt wird nur dieser Ordner. Für ein allgemeines Postfach legen Sie einen zweiten Eintrag mit dem Rechnungsordner an.',
        'note_inbox' => 'Mails ohne Rechnung landen wie immer in der Integrations-Inbox.',
        'note_duplicates' => 'Schon übernommene Mails und Dateien werden erkannt und nicht doppelt angelegt.',
        'note_graph_limit' => 'Microsoft 365 liefert je Durchgang höchstens 5.000 Mails.',
    ],
    'error' => [
        'folder_missing' => 'Der Ordner „:folder“ wurde im Postfach nicht gefunden.',
    ],
    'reference' => [
        'customer_number' => 'Kundennummer im Text: :number',
        'invoice_number' => 'Rechnungsnummer im Text: :number',
        'project_number' => 'Projektnummer im Text: :number',
    ],
];
