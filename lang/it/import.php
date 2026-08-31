<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : import.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'entity' => [
        'customers' => 'Clienti',
        'suppliers' => 'Fornitori',
        'articles' => 'Articoli',
        'projects' => 'Progetti',
        'users' => 'Utenti',
        'materials' => 'Materiali',
        'vehicles' => 'Veicoli',
        'scheduled_shifts' => 'Piani turni',
        'tours' => 'Giri',
        'remote_sessions' => 'Sessioni di manutenzione remota',
        'attendances' => 'Timbrature',
        'project_times' => 'Tempi di progetto',
        // MVP-707 (Vollscan H20): Altsystem-Übernahme.
        'invoices' => 'Fatture pregresse (partite aperte)',
        'quotes' => 'Preventivi',
        'assets' => 'Asset',
        'contact_persons' => 'Referenti',
        'documents' => 'Documenti (ZIP)',
    ],
    'template' => [
        'example_required' => 'Valore di esempio (obbligatorio)',
        'example_optional' => 'Valore di esempio (facoltativo)',
        'download' => 'Scarica il modello',
    ],

    'state' => [
        'preflight' => 'Controllo preliminare',
        'awaitingApproval' => 'In attesa di approvazione',
        'running' => 'In corso',
        'succeeded' => 'Riuscito',
        'partial' => 'Parziale',
        'failed' => 'Fallito',
    ],
    'errorCode' => [
        'required' => 'Campo obbligatorio mancante',
        'format' => 'Errore di formato',
        'unique' => 'Valore non univoco',
        'fkMissing' => 'Riferimento non trovato',
        'tooLong' => 'Valore troppo lungo',
        'outOfRange' => 'Valore fuori intervallo',
        'persist' => 'Errore di persistenza',
        'headerMissing' => 'Colonna mancante',
        'headerUnknown' => 'Colonna sconosciuta',
        'periodLocked' => 'Periodo bloccato',
        'skipped' => 'Ignorato',
        'blocked' => 'Bloccato',
    ],
    'error' => [
        'email_taken' => 'Questo indirizzo e-mail è già in uso.',
        'required' => 'Il campo obbligatorio :field è mancante.',
        'tooLong' => 'Il campo :field supera la lunghezza massima di :max caratteri.',
        'header' => [
            'missing' => 'La colonna obbligatoria :column è mancante nell\'intestazione CSV.',
            'duplicate' => 'La colonna :column compare più volte.',
        ],
        'format' => [
            'default' => 'Il campo :field ha un formato non valido (:reason).',
            'email' => 'Indirizzo e-mail non valido.',
            'country' => 'Il codice paese deve avere 2-3 lettere maiuscole (ISO 3166-1).',
            'currency' => 'Il codice valuta deve avere 3 lettere maiuscole (ISO 4217).',
            'enum' => 'Il valore non è uno stato valido.',
            'parse' => 'Impossibile analizzare il file: :reason',
            'xlsxUnreadable' => 'Il file Excel è danneggiato o non è un formato XLSX valido.',
            'xlsxEmpty' => 'Il primo foglio di lavoro del file Excel non contiene righe.',
            'date' => 'Data non valida (atteso es. «28.05.2026, 09:42:09»).',
            'time' => 'Ora non valida (atteso HH:MM).',
            'status' => 'Il valore non è uno stato valido.',
            'amount' => 'Importo non valido.',
            'url' => 'Indirizzo non valido (atteso http:// o https://).',
        ],
        'outOfRange' => [
            'rowLimit' => 'Limite di righe (:max) superato — resto ignorato.',
            'contactPersons' => 'Non sono previsti più di :max referenti per cliente/fornitore.',
        ],
        'fkMissing' => [
            'customer' => 'Nessun cliente con il numero :number trovato.',
            'supplier' => 'Nessun fornitore con il numero :number trovato.',
            'asset' => 'Nessun asset con il numero :number trovato.',
            'article' => 'Nessun articolo con il numero :number trovato.',
            'projectNumber' => 'Nessun progetto con il numero :number trovato.',
            'customerName' => 'Nessun cliente univoco con nome «:value» trovato.',
            'user' => 'Nessun utente con l\'e-mail :value trovato.',
            'project' => 'Nessun progetto «:value» trovato — riga inviata alla casella di assegnazione.',
        ],
        // MVP-707: Altsystem-Übernahme (Rechnungshoheit, Altrechnungen, Dokument-ZIP).
        'blocked' => [
            'invoiceSovereignty' => 'La fatturazione è gestita da :program — le fatture pregresse locali sono bloccate per questo cliente.',
        ],
        'invoice' => [
            'amountMissing' => 'Manca l\'importo lordo o netto (con aliquota).',
            'paidExceedsTotal' => 'L\'importo pagato (:paid) supera l\'importo della fattura (:total).',
            'numberTaken' => 'Il numero di fattura :number è già in uso.',
        ],
        'document' => [
            'manifestMissing' => 'Il file ZIP non contiene un manifest.csv.',
            'fileMissing' => 'Il file «:file» non è contenuto nello ZIP.',
            'extension' => 'L\'estensione «:ext» non è consentita.',
            'mime' => 'Il contenuto del file (:mime) non è consentito.',
            'targetType' => 'Il tipo di destinazione deve essere customer, project o asset.',
            'noContent' => 'I documenti possono essere importati solo tramite l\'import ZIP (manifest.csv + file).',
            'zipUnreadable' => 'Impossibile leggere il file ZIP: :reason',
            'tooLarge' => 'Il file «:file» supera il limite di :max MB.',
            'noActor' => 'Esecuzione di import senza utente — i documenti richiedono un autore.',
        ],
        'persist' => [
            'noBookingUser' => 'Nessun utente imputabile trovato nell\'organizzazione.',
        ],
        // MVP-438: blocco GoBD — nessuna sovrascrittura silenziosa di periodi verificati.
        'periodLocked' => [
            'attendance' => 'Il giorno :date è bloccato dalla chiusura giornaliera o dall\'approvazione mensile — riga ignorata.',
            'projectTime' => 'Il periodo :date è già chiuso/esportato — riga ignorata.',
        ],
        // MVP-438: righe di avviso iCal (mappatura volutamente prudente).
        'ical' => [
            'allDay' => 'Evento di intera giornata «:event» ignorato (non conteggiabile come presenza).',
            'noTime' => 'Evento «:event» senza orario ignorato.',
            'category' => 'Evento «:event» fuori dall\'elenco di categorie consentite ignorato.',
            'transparent' => 'Evento «:event» contrassegnato come libero/assente ignorato.',
            'recurring' => 'Evento ricorrente «:event»: importata solo l\'istanza base (l\'espansione della serie verrà in seguito).',
            'unsupportedEntity' => 'L\'importazione iCal non è supportata per questo tipo di importazione.',
        ],
    ],

    // MVP-707: Upload-Hinweise je Dateiart + Texte der Altrechnungs-Übernahme.
    'upload' => [
        'csv' => 'File CSV, Excel o iCal (.csv, .xlsx, .ics, max. :mb MB, :rows righe)',
        'zip' => 'File ZIP con manifest.csv e i file dei documenti (.zip, max. :mb MB, :entries file)',
        'zipHint' => 'Ogni riga del manifest.csv (modello sopra) fa riferimento a un file nello ZIP e lo assegna a cliente, progetto o asset.',
    ],
    'legacy' => [
        'position' => 'Ripresa dal sistema precedente — fattura :number',
        'note' => 'Fattura pregressa ripresa da :source (partita aperta di apertura, nessuna registrazione a giornale).',
    ],
];
