<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : hr.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Fascicolo personale digitale (Feature 141, MVP-708).
    'personnel_file' => [
        'title' => 'Fascicolo personale',
        'title_mine' => 'Il mio fascicolo personale',
        'nav' => 'Il mio fascicolo personale',
        'subtitle' => 'Fascicolo personale di :name — riservato, visibile solo alla cerchia HR e alla persona interessata.',
        'subtitle_mine' => 'Il Suo fascicolo personale: consultare, confermare la lettura e presentare documenti.',
        'back' => 'Torna all\'elenco del personale',
        'empty' => 'Nessun documento nel fascicolo personale.',
        'confidential_fixed' => 'I fascicoli personali sono sempre riservati — l\'interruttore è omesso, il contrassegno è imposto.',
        'retention_pending' => 'dalla cessazione',
        'confirm_delete' => 'Distruggere definitivamente questo documento dal fascicolo personale? File e versioni vengono eliminati; il registro di audit rimane.',
        'field' => [
            'is_ack_required' => 'Richiedi conferma di lettura',
            'note' => 'Nota',
            'review_note' => 'Motivo del rifiuto',
            'title' => 'Titolo',
            'category' => 'Categoria',
            'validity' => 'Validità',
            'valid_from' => 'Valido dal',
            'valid_until' => 'Valido fino al',
            'retention_until' => 'Conservazione fino al',
            'version' => 'Versione',
            'updated_at' => 'Aggiornato',
            'description' => 'Descrizione',
            'file' => 'File',
            'version_note' => 'Nota di versione',
            'documents' => 'Documenti',
        ],
        'action' => [
            'submit' => 'Presenta un documento',
            'accept' => 'Acquisisci',
            'reject' => 'Rifiuta',
            'acknowledge' => 'Letto',
            'upload' => 'Aggiungi documento',
            'edit' => 'Modifica',
            'save' => 'Salva',
            'download' => 'Scarica',
            'versions' => 'Versioni',
            'delete' => 'Distruggi',
        ],
        'flash' => [
            'submitted' => 'Il documento è stato presentato; l’ufficio del personale decide sull’acquisizione.',
            'accepted' => 'La presentazione è stata acquisita nel fascicolo personale.',
            'rejected' => 'La presentazione è stata rifiutata.',
            'acknowledged' => 'La conferma di lettura è stata salvata.',
            'created' => 'Il documento è stato aggiunto al fascicolo personale.',
            'updated' => 'Il documento del fascicolo personale è stato aggiornato.',
        ],
        'hint' => [
            'ack' => 'La persona interessata conferma la lettura nel proprio fascicolo; una nuova versione richiede una nuova conferma.',
            'submit' => 'L’ufficio del personale esamina il documento e lo acquisisce nel Suo fascicolo oppure lo rifiuta indicando il motivo.',
        ],
        'ack' => [
            'open' => 'Conferma di lettura in sospeso',
            'done' => 'Letto il :date',
            'confirm' => 'Confermi di aver letto questo documento?',
        ],
        'submission' => [
            'title' => 'Presentazioni',
            'subtitle' => 'Documenti presentati dai dipendenti in attesa di acquisizione nel fascicolo personale.',
            'person' => 'Persona',
            'submitted_at' => 'Presentato il',
            'empty' => 'Nessuna presentazione in sospeso.',
            'reason' => 'Rifiutato: :reason',
        ],
        'error' => [
            'ack_not_requested' => 'Per questo documento non è stata richiesta alcuna conferma di lettura.',
            'submission_decided' => 'Su questa presentazione è già stato deciso.',
            'submission_file_missing' => 'Il file presentato non esiste più.',
        ],
        'notification' => [
            'ack_requested_title' => 'Conferma di lettura richiesta: :title',
            'ack_requested_message' => 'Confermi nel Suo fascicolo personale di aver letto il documento.',
            'submission_received_title' => 'Nuovo documento presentato per un fascicolo personale',
            'submission_received_message' => 'La presentazione è in attesa di accettazione o rifiuto.',
            'submission_accepted_title' => 'Inserito nel fascicolo personale: :title',
            'submission_rejected_title' => 'Non inserito nel fascicolo personale: :title',
            'submission_rejected_message' => 'Motivazione: :reason',
        ],
    ],
    // Personal-Kapazität (MVP-940).
    'capacity' => [
        'title' => 'Capacità del personale',
        'button' => 'Capacità',
        'subtitle' => 'Fabbisogno pianificato (ordini assegnati) rispetto alle ore previste dei membri per settimana; festività e ferie approvate detratte.',
        'team' => 'Team',
        'week' => 'Settimana dal :date',
        'members' => ':count membri',
        'empty' => 'Nessun team finora.',
        'hint' => 'Valori in ore: pianificato / disponibile.',
        'open_requisitions' => 'Posizioni aperte in totale: :count.',
    ],
    // Vertretungen beim Austritt (MVP-941).
    'offboarding' => [
        'deputies' => 'Riassegnare le sostituzioni',
        'deputies_hint' => 'Queste persone hanno indicato il membro uscente come sostituto. Senza scelta la sostituzione termina.',
        'deputy_for' => 'Nuovo sostituto per :name',
        'no_deputy' => '— nessun sostituto —',
    ],
    // Arbeitsvertrag zur Unterschrift (MVP-939).
    'employment' => [
        'title' => 'Contratto di lavoro da firmare',
        'intro' => 'Il contratto viene inviato tramite link alla persona; poi l\'organizzazione controfirma. La versione firmata viene archiviata nel fascicolo personale.',
        'send' => 'Invia per la firma',
        'default_title' => 'Contratto di lavoro :name',
        'default_declaration' => 'Ho letto il contratto di lavoro e lo accetto.',
        'filed_note' => 'Versione firmata dal contratto :number.',
        'field' => [
            'title' => 'Denominazione',
            'starts_on' => 'Inizio',
            'email' => 'E-mail della persona',
            'declaration_text' => 'Dichiarazione di consenso',
            'file' => 'Contratto (PDF)',
        ],
        'flash' => [
            'sent' => 'Contratto di lavoro inviato a :email per la firma.',
        ],
        'error' => [
            'email' => 'Indichi un indirizzo e-mail.',
        ],
    ],
];
