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
    'intro' => 'Connessioni corriere per etichette di spedizione e tracciamento delle spedizioni (DHL Paket, UPS, FedEx). Una connessione per corriere e organizzazione; le credenziali sono memorizzate cifrate.',

    'form_heading' => 'Aggiungi / modifica connessione',
    'form_hint' => 'Scelga il corriere e inserisca le relative credenziali. Salvando di nuovo con lo stesso corriere si aggiorna la connessione esistente.',
    'secret_hint' => 'La password e la chiave API vengono memorizzate cifrate e non vengono più mostrate. Le lasci vuote durante la modifica per mantenere i valori salvati.',
    'connections_heading' => 'Connessioni esistenti',
    'no_connections' => 'Nessuna connessione corriere ancora configurata.',

    'field' => [
        'carrier' => 'Corriere',
        'name' => 'Denominazione',
        'username' => 'Utente / ID client',
        'password' => 'Password / secret client',
        'api_key' => 'Chiave API (solo DHL: dhl-api-key)',
        'billing_number' => 'Numero di fatturazione / conto',
        'sandbox' => 'Sandbox / ambiente di test',
        'active' => 'Attivo',
        'weight_grams' => 'Peso (g)',
        'length_cm' => 'Lunghezza (cm)',
        'width_cm' => 'Larghezza (cm)',
        'height_cm' => 'Altezza (cm)',
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
        'credentials_required' => 'Per una nuova connessione sono obbligatori utente/ID client e password/secret client (DHL in più: chiave API).',
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
    'parcel' => [
        'add' => 'Aggiungi collo',
        'edit' => 'Modifica collo :no',
        'delete' => 'Elimina collo',
        'confirm_delete' => 'Eliminare il collo :no? I suoi numeri di serie tornano liberi.',
        'label' => 'Collo :no di :of',
        'serials' => 'Numeri di serie nel collo',
        'no_serials' => 'Questa consegna non ha numeri di serie liberi.',
        'serial_count' => ':count n. di serie',
        'saved' => 'Collo salvato.',
        'deleted' => 'Collo eliminato.',
        'serial_not_allowed' => 'I numeri di serie devono provenire da questa consegna e non possono trovarsi in un altro collo.',
        'locked' => 'Per questa consegna esiste già un ordine di spedizione; i colli sono fissati.',
    ],
];
