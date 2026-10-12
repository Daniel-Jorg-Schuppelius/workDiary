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
    'intro' => 'Le caselle vengono interrogate regolarmente dall’ultimo punto raggiunto, anche le e-mail già lette. Le nuove e-mail arrivano come proposte nella inbox di integrazione, le fatture tra le fatture in entrata; le e-mail elaborate vengono solo contrassegnate o spostate, mai eliminate.',
    'to_inbox' => 'Vai alla inbox di associazione',
    'title_edit' => 'Modifica casella',
    'to_inbox_count' => 'Vai alla inbox di associazione (:count)',

    'mailboxes_heading' => 'Caselle',
    'no_connections' => 'Nessuna casella collegata finora.',
    'add_heading' => 'Aggiungi casella',

    'inbox' => [
        'invoice_action' => 'Acquisisci come fattura in entrata',
        'no_subject' => '(senza oggetto)',
        'invoice_mailbox' => 'Casella delle fatture · :from',
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
        'mailbox_address' => 'Indirizzo della casella',
        'mailbox_address_hint' => 'Lasci vuoto per la casella dell’account collegato. Per una casella condivisa (ad es. fatture@…) l’account necessita dell’accesso completo.',
        'password_keep' => 'Lasci vuoto per mantenere la password salvata.',
        'fetch_since' => 'Recupera dal',
        'fetch_since_hint' => 'Le e-mail ricevute prima di questo giorno non vengono recuperate. Una data precedente recupera e-mail più vecchie.',
        'folder_hint_list' => 'I suggerimenti provengono dalla casella; separi le sottocartelle con «/», ad es. Posta in arrivo/Fatture.',
        'folder_hint_error' => 'Impossibile caricare l’elenco delle cartelle (:error). Inserisca la cartella manualmente e separi le sottocartelle con «/».',
        'folder_hint_free' => 'Dopo il primo salvataggio, la finestra propone le cartelle della casella. Separi le sottocartelle con «/», ad es. INBOX/Fatture.',
        'einvoice_intake' => 'Casella fatture: acquisire le fatture dagli allegati tra le fatture in entrata',
        'einvoice_intake_hint' => 'Ogni file PDF, XML o immagine di un’e-mail diventa una fattura in entrata. Scelga preferibilmente una cartella dedicata in cui una regola smisti le fatture.',
        'callreport_intake' => 'Casella rapporti chiamate: acquisire gli elenchi chiamate FRITZ!Box (CSV) nell’importazione elenchi chiamate',
        'name' => 'Etichetta',
        'transport' => 'Trasporto',
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
        'create' => 'Nuova casella',
        'edit' => 'Modifica',
        'backfill' => 'Recupera e-mail precedenti',
        'activate' => 'Attiva',
        'poll' => 'Interroga ora',
        'disconnect' => 'Disconnetti',
        'save' => 'Salva',
    ],

    'col' => [
        'purpose' => 'Utilizzo',
        'fetch_since' => 'dal :date',
        'host' => 'Account',
        'status' => 'Stato',
        'last_polled' => 'Ultima interrogazione',
    ],

    'status' => [
        'error' => 'Errore',
        'backfill_running' => 'Recupero in corso · :count elaborate',
        'active' => 'Attivo',
        'inactive' => 'Inattivo',
    ],

    'flash' => [
        'invoice_imported' => 'Acquisito tra le fatture in entrata: :count.',
        'invoice_duplicate' => 'Questa fattura è già tra le fatture in entrata.',
        'invoice_none' => 'Nessun allegato è un file PDF, XML o immagine.',
        'invoice_failed' => 'Acquisizione tra le fatture in entrata non riuscita.',
        'backfill_started' => 'Recupero avviato. I prossimi recuperi elaborano le e-mail precedenti a lotti.',
        'activated' => 'Casella attivata.',
        'chained_folders' => 'Nota: una cartella di questa casella è anche la cartella di recupero o di elaborate di «:name». Le e-mail passerebbero da una voce all’altra.',
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
    'transport' => [
        'imap' => 'Casella IMAP',
    ],
    'access' => [
        'own_mailbox' => 'Casella dell’account collegato',
    ],
    'group' => [
        'mailbox' => 'Casella',
        'folders' => 'Cartelle e data di inizio',
        'purpose' => 'Utilizzo',
    ],
    'purpose' => [
        'invoices' => 'Fatture',
        'callreports' => 'Elenchi chiamate',
        'inbox' => 'Inbox',
    ],
    'validation' => [
        'duplicate_folder' => 'Esiste già una voce con questa cartella per questo accesso.',
    ],
    'backfill' => [
        'title' => 'Recupera e-mail precedenti',
        'submit' => 'Recupera',
        'intro' => 'I prossimi recuperi elaborano tutte le e-mail della cartella «:folder» a partire dal giorno scelto, anche quelle già lette.',
        'note_folder' => 'Viene recuperata solo questa cartella. Per una casella generica crei una seconda voce con la cartella delle fatture.',
        'note_inbox' => 'Le e-mail senza fattura arrivano come sempre nella inbox di integrazione.',
        'note_duplicates' => 'E-mail e file già acquisiti vengono riconosciuti e non creati due volte.',
        'note_graph_limit' => 'Microsoft 365 fornisce al massimo 5.000 e-mail per passaggio.',
    ],
    'error' => [
        'folder_missing' => 'La cartella «:folder» non è stata trovata nella casella di posta.',
    ],
    'reference' => [
        'customer_number' => 'Numero cliente nel testo: :number',
        'invoice_number' => 'Numero fattura nel testo: :number',
        'project_number' => 'Numero progetto nel testo: :number',
    ],
];
