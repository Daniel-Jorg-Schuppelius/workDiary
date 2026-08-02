<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : disposal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Pratica di smaltimento (feature 100, MVP-474/475): elenco, pratica, dialoghi
// e PDF dell'attestato cliente. Label enum e messaggi backend inline nel codice.
return [
    'eyebrow' => 'Smaltimento',

    'index' => [
        'title' => 'Pratiche di smaltimento',
        'subtitle' => 'Ritiro, elenco apparecchi, trattamento dei supporti dati e attestati dello smaltitore — tracciabile fino all\'attestato cliente.',
        'empty' => 'Nessuna pratica di smaltimento — creare la prima pratica tramite il dialogo.',
        'kpi' => [
            'open' => 'Pratiche aperte',
            'hazardous_open' => 'Aperte con rifiuti pericolosi',
            'completed_year' => 'Concluse (anno corrente)',
        ],
        'filter' => [
            'hazardous_only' => 'solo pericolosi',
        ],
        'col' => [
            'items' => 'Posizioni',
            'picked_up' => 'Ritiro',
        ],
    ],

    'field' => [
        'site' => 'Sede di intervento',
        'diary_entry' => 'Incarico',
        'picked_up_on' => 'Data di ritiro',
        'total_weight' => 'Peso totale (kg)',
        'created' => 'Creata',
        'cancelled_at' => 'Annullata il',
        'cancel_reason' => 'Motivo dell\'annullamento',
        'completed_at' => 'Conclusa il',
        'completed_by' => 'Conclusa da',
    ],

    'form' => [
        'title_create' => 'Nuova pratica di smaltimento',
        'title_edit' => 'Modifica pratica di smaltimento',
        'submit_create' => 'Crea pratica',
        'group_assignment' => 'Cliente e intervento',
        'group_pickup' => 'Ritiro e dettagli',
        'site' => 'Sede di intervento (opzionale)',
        'site_none' => 'senza sede di intervento',
        'diary_entry' => 'Incarico/fascicolo (opzionale)',
        'diary_entry_none' => 'senza riferimento all\'incarico',
    ],

    'show' => [
        'nav' => 'Pratica di smaltimento',
        'title' => 'Pratica di smaltimento :number',
        'section' => [
            'job' => 'Pratica',
            'blockers' => 'Verifica di chiusura',
            'items' => 'Elenco apparecchi',
            'handovers' => 'Consegne allo smaltitore',
            'signature' => 'Conferma di presa in carico',
            'record' => 'Attestato cliente',
        ],
    ],

    'badge' => [
        'hazardous' => 'pericoloso',
        'signed' => 'Presa in carico firmata',
    ],

    'item' => [
        'title_create' => 'Registra posizione',
        'title_edit' => 'Modifica posizione',
        'group_device' => 'Apparecchio',
        'group_disposal' => 'Smaltimento e supporti dati',
        'weight' => 'Peso (kg)',
        'condition_note' => 'Nota sullo stato',
        'avv_code' => 'Codice rifiuto (AVV/CER)',
        'avv_hint' => 'Asterisco * = rifiuto pericoloso — la classificazione viene derivata automaticamente.',
        'has_data_storage' => 'L\'apparecchio contiene supporti dati',
        'note' => 'Nota',
        'empty' => 'Nessuna posizione apparecchio — aggiungere apparecchi tramite «Registra posizione».',
        'col' => [
            'device' => 'Produttore/modello',
            'weight' => 'Peso (kg)',
            'avv' => 'Codice rifiuto (AVV/CER)',
            'data_storage' => 'Supporti dati',
        ],
        'treatments_count' => '1 trattamento|:count trattamenti',
        'treatment_missing' => 'Trattamento mancante',
    ],

    'treatment' => [
        'title_create' => 'Registra trattamento supporto dati',
        'group_method' => 'Procedura e norma',
        'group_evidence' => 'Esecuzione e attestato',
        'media_type' => 'Tipo di supporto dati',
        'method' => 'Procedura',
        'din_category' => 'Categoria di materiale DIN 66399',
        'security_level' => 'Livello di sicurezza (1–7)',
        'protection_class' => 'Classe di protezione',
        'protection_class_none' => 'senza indicazione',
        'protection_class_short' => 'Classe di protezione :class',
        'treated_at' => 'Data/ora',
        'performed_by' => 'Esecutore',
        'evidence_reference' => 'Riferimento attestato/certificato',
        'please_select' => '-- selezionare --',
    ],

    'handover' => [
        'title_create' => 'Registra consegna allo smaltitore',
        'group_proof' => 'Smaltitore e attestato',
        'group_attachment' => 'Documento e nota',
        'disposer' => 'Smaltitore',
        'proof_type' => 'Tipo di attestato',
        'document_number' => 'Numero documento',
        'handed_over_on' => 'Data di consegna',
        'certificate_reference' => 'Riferimento certificato EfbV',
        'proof_file' => 'File attestato (opzionale)',
        'proof_file_hint' => 'PDF, JPG o PNG — massimo 10 MB. L\'attestato viene archiviato come documento DMS.',
        'note' => 'Nota',
        'no_disposers' => 'Nessuna impresa di smaltimento certificata registrata.',
        'create_disposer' => 'Crea lo smaltitore come contatto esterno',
        'empty' => 'Nessuna consegna a uno smaltitore ancora registrata.',
        'col' => [
            'disposer' => 'Smaltitore',
            'proof_type' => 'Tipo di attestato',
            'document_number' => 'Numero documento',
            'certificate' => 'Riferimento EfbV',
            'document' => 'Documento DMS',
        ],
    ],

    'sign' => [
        'signer_name' => 'Nome della persona che prende in carico',
        'signed_at' => 'Firmato il',
        'hash' => 'Checksum',
        'hint' => 'Con «Conferma presa in carico» la firma viene salvata in modo verificabile.',
        'missing' => 'Nessuna firma di presa in carico presente.',
    ],

    'record' => [
        'released_hint' => 'L\'attestato cliente è pubblicato nel portale clienti.',
        'pending_hint' => 'L\'attestato cliente viene generato automaticamente alla chiusura della pratica.',
    ],

    'cancel' => [
        'title' => 'Annulla pratica di smaltimento',
        'intro' => 'L\'annullamento è definitivo e viene registrato con motivazione nella catena di tracciabilità.',
        'reason' => 'Motivazione',
    ],

    'action' => [
        'create' => 'Nuova pratica di smaltimento',
        'collect' => 'Registra ritiro',
        'start_treatment' => 'Avvia trattamento',
        'hand_over' => 'Consegna allo smaltitore',
        'pdf_preview' => 'PDF attestato (anteprima)',
        'add_item' => 'Registra posizione',
        'add_treatment' => 'Registra trattamento',
        'add_handover' => 'Registra consegna',
        'sign' => 'Conferma presa in carico',
    ],

    'confirm' => [
        'complete' => 'Concludere la pratica? L\'attestato cliente viene generato e pubblicato e gli asset collegati vengono dismessi.',
        'delete_item' => 'Rimuovere davvero questa posizione apparecchio?',
        'delete_treatment' => 'Rimuovere davvero questo trattamento del supporto dati?',
        'delete_handover' => 'Rimuovere davvero questa consegna allo smaltitore?',
    ],

    'pdf' => [
        'title' => 'Attestato di presa in carico e smaltimento',
        'number' => 'Numero pratica',
        'customer' => 'Cliente',
        'picked_up_on' => 'Data di ritiro',
        'responsible' => 'Responsabile',
        'status' => 'Stato',
        'total_weight' => 'Peso totale',
        'items' => 'Elenco apparecchi',
        'treatments' => 'Attestato di protezione dati e supporti dati (DIN 66399)',
        'handovers' => 'Attestato di smaltimento e destinazione',
        'confirmation' => 'Conferma',
        'customer_signature' => 'Presa in carico da parte del cliente',
        'not_signed' => 'Non firmato.',
        'provider' => 'Fornitore di servizi',
        'completed_at' => 'Conclusa il',
        'hazardous_suffix' => '(pericoloso)',
        'col' => [
            'category' => 'Categoria',
            'device' => 'Produttore/modello',
            'serial' => 'Numero di serie',
            'quantity' => 'Quantità',
            'weight' => 'Peso (kg)',
            'avv' => 'Codice rifiuto (AVV/CER)',
            'media_type' => 'Tipo di supporto',
            'method' => 'Procedura',
            'din' => 'DIN 66399',
            'protection_class' => 'Classe di protezione',
            'treated_at' => 'Data/ora',
            'performed_by' => 'Esecutore',
            'evidence' => 'N. attestato/certificato',
            'disposer' => 'Smaltitore',
            'proof_type' => 'Tipo di attestato',
            'document_number' => 'Numero documento',
            'handed_over_on' => 'Data',
            'certificate' => 'Certificato EfbV',
        ],
        'footer' => [
            'hash' => 'Checksum',
            'generated' => 'Generato il :at',
        ],
    ],
];
