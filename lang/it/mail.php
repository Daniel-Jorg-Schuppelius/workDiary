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
    'title' => 'Ricezione e-mail',
    'intro' => 'Le caselle IMAP collegate vengono interrogate dallo scheduler; le nuove e-mail arrivano come proposte nella inbox di integrazione e vengono associate a un cliente — mai create alla cieca. Le e-mail elaborate vengono solo contrassegnate/spostate, mai eliminate. WorkDiary non è un client di posta.',
    'to_inbox' => 'Vai alla inbox di associazione',

    'mailboxes_heading' => 'Caselle',
    'no_connections' => 'Nessuna casella collegata finora.',
    'add_heading' => 'Aggiungi casella',

    'inbox' => [
        'no_subject' => '(senza oggetto)',
        'book_action' => 'Registra come nota di comunicazione',
        'book_ticket_action' => 'Registra come ticket di servizio',
        'book_customer_placeholder' => '… cliente (vuoto = mittente rilevato)',
    ],

    'dms' => [
        'action' => 'Importa nell’archivio documenti',
        'origin' => 'Importato dall’e-mail: :subject (Message-ID :message_id)',
        'imported' => ':count allegato/i importato/i nell’archivio documenti.',
        'none' => 'Nessun allegato importabile disponibile.',
    ],

    'encryption' => [
        'none' => 'Nessuna',
    ],

    'field' => [
        'name' => 'Etichetta',
        'host' => 'Server IMAP',
        'port' => 'Porta',
        'encryption' => 'Cifratura',
        'username' => 'Nome utente',
        'password' => 'Password',
        'folder' => 'Cartella',
        'processed_folder' => 'Cartella di destinazione (elaborate)',
        'processed_folder_placeholder' => 'facoltativa, ad es. Elaborate',
        'active' => 'Attivo',
    ],

    'action' => [
        'poll' => 'Interroga ora',
        'disconnect' => 'Disconnetti',
        'save' => 'Salva',
    ],

    'col' => [
        'host' => 'Account',
        'status' => 'Stato',
        'last_polled' => 'Ultima interrogazione',
    ],

    'status' => [
        'active' => 'Attivo',
        'inactive' => 'Inattivo',
    ],

    'flash' => [
        'saved' => 'Casella salvata.',
        'disconnected' => 'Casella disconnessa.',
        'polled' => 'Interrogazione avviata.',
        'booked' => 'E-mail registrata come voce di comunicazione.',
        'book_failed' => 'Registrazione non riuscita.',
        'ticket_booked' => 'E-mail registrata come ticket di servizio.',
        'ticket_failed' => 'Registrazione del ticket non riuscita.',
        'dms_failed' => 'Importazione nell’archivio documenti non riuscita.',
        'already_resolved' => 'Questa voce è già risolta.',
        'password_required' => 'Una nuova casella richiede una password.',
        'customer_required' => 'Nessun cliente associato.',
    ],
    'reference' => [
        'customer_number' => 'Numero cliente nel testo: :number',
        'invoice_number' => 'Numero fattura nel testo: :number',
        'project_number' => 'Numero progetto nel testo: :number',
    ],
];
