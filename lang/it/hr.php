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
        'subtitle_mine' => 'Il suo fascicolo personale (accesso personale, sola lettura).',
        'back' => 'Torna all\'elenco del personale',
        'empty' => 'Nessun documento nel fascicolo personale.',
        'confidential_fixed' => 'I fascicoli personali sono sempre riservati — l\'interruttore è omesso, il contrassegno è imposto.',
        'retention_pending' => 'dalla cessazione',
        'confirm_delete' => 'Distruggere definitivamente questo documento dal fascicolo personale? File e versioni vengono eliminati; il registro di audit rimane.',
        'field' => [
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
            'upload' => 'Aggiungi documento',
            'edit' => 'Modifica',
            'save' => 'Salva',
            'download' => 'Scarica',
            'versions' => 'Versioni',
            'delete' => 'Distruggi',
        ],
        'flash' => [
            'created' => 'Il documento è stato aggiunto al fascicolo personale.',
            'updated' => 'Il documento del fascicolo personale è stato aggiornato.',
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
