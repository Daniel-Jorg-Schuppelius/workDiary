<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : shipping.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Spedizione e logistica',
    'intro' => 'Connessioni corriere per etichette di spedizione e tracciamento delle spedizioni (DHL Paket e altri). Una connessione per corriere e organizzazione; le credenziali sono memorizzate cifrate.',

    'form_heading' => 'Aggiungi / modifica connessione',
    'form_hint' => 'Scegli il corriere e inserisci le sue credenziali. Salvando di nuovo con lo stesso corriere si aggiorna la connessione esistente.',
    'secret_hint' => 'La password e la chiave API vengono memorizzate cifrate e non vengono più mostrate. Lasciale vuote durante la modifica per mantenere i valori salvati.',
    'connections_heading' => 'Connessioni esistenti',
    'no_connections' => 'Nessuna connessione corriere ancora configurata.',

    'field' => [
        'carrier' => 'Corriere',
        'name' => 'Denominazione',
        'username' => 'Utente (account aziendale)',
        'password' => 'Password',
        'api_key' => 'Chiave API (dhl-api-key)',
        'billing_number' => 'Numero di fatturazione',
        'sandbox' => 'Sandbox / ambiente di test',
        'active' => 'Attivo',
        'weight_grams' => 'Peso (g)',
    ],

    'label_short' => 'Spedizione',

    'col' => [
        'mode' => 'Modalità',
        'status' => 'Stato',
    ],

    'mode' => [
        'sandbox' => 'Sandbox',
        'production' => 'Produzione',
    ],

    'status_label' => [
        'active' => 'Attivo',
        'inactive' => 'Inattivo',
    ],

    'action' => [
        'save' => 'Salva',
        'disconnect' => 'Disattiva',
        'create' => 'Spedisci',
    ],

    'flash' => [
        'saved' => 'Connessione corriere salvata.',
        'disconnected' => 'Connessione corriere disattivata.',
        'credentials_required' => 'Utente, password e chiave API sono obbligatori per una nuova connessione.',
        'no_recipient' => 'La consegna non ha un cliente come destinatario.',
        'already_created' => 'Esiste già una spedizione per questa consegna.',
        'no_connection' => 'Nessuna connessione attiva configurata per il corriere selezionato.',
        'label_created' => 'Spedizione creata ed etichetta recuperata.',
        'label_failed' => 'Impossibile creare l\'etichetta di spedizione: :reason',
    ],

    'notify' => [
        'delivery_problem' => [
            'title' => 'Problema di consegna di una spedizione',
            'message' => 'La spedizione :tracking (:carrier) segnala un problema di consegna.',
        ],
    ],

    // Stato spedizione (ShipmentStatus).
    'status' => [
        'draft' => 'Bozza',
        'labeled' => 'Etichetta creata',
        'in_transit' => 'In transito',
        'delivered' => 'Consegnato',
        'problem' => 'Problema di consegna',
        'cancelled' => 'Annullato',
    ],
];
