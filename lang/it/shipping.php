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

    'form_heading' => 'Aggiungi connessione',
    'form_heading_edit' => 'Modifica connessione :carrier',
    'form_hint' => 'Scelga il corriere e inserisca le relative credenziali. Modifichi le connessioni esistenti tramite «Modifica» nell’elenco.',
    'secret_hint' => 'La password e la chiave API vengono memorizzate cifrate e non vengono più mostrate. Le lasci vuote durante la modifica per mantenere i valori salvati.',
    'connections_heading' => 'Connessioni esistenti',
    'no_connections' => 'Nessuna connessione corriere ancora configurata.',

    'field' => [
        'carrier' => 'Corriere',
        'name' => 'Denominazione',
        'username' => 'Utente / ID client',
        'password' => 'Password / secret client',
        'api_key' => 'Chiave API (solo DHL: dhl-api-key)',
        'returns_receiver_id' => 'ID destinatario resi (solo DHL)',
        'returns_receiver_id_hint' => 'Destinatario dei resi creato nel portale clienti business DHL; necessario per le etichette di reso.',
        'billing_number' => 'Numero di fatturazione / conto',
        'sandbox' => 'Sandbox / ambiente di test',
        'active' => 'Attivo',
        'weight_grams' => 'Peso (g)',
        'length_cm' => 'Lunghezza (cm)',
        'width_cm' => 'Larghezza (cm)',
        'height_cm' => 'Altezza (cm)',
    ],

    'label_short' => 'Spedizione',
    'last_tracked' => 'Ultima verifica: :time',
    'confirm_cancel' => 'Annullare la spedizione presso il corriere? L’etichetta non sarà più valida; dopo potrà creare una nuova spedizione.',

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
        'edit' => 'Modifica',
        'cancel_edit' => 'Annulla',
        'download_label' => 'Scarica etichetta',
        'track_now' => 'Verifica stato spedizione',
        'cancel_shipment' => 'Annulla spedizione',
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
        'tracked' => 'Stato della spedizione verificato: :status',
        'track_failed' => 'Impossibile verificare lo stato della spedizione: :reason',
        'cancelled' => 'Spedizione annullata.',
        'cancel_failed' => 'Impossibile annullare la spedizione: :reason',
        'not_cancellable' => 'La spedizione è già presso il corriere e non può più essere annullata.',
        'exists_use_edit' => 'Esiste già una connessione per questo corriere. La modifichi tramite «Modifica».',
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
    'customs' => [
        'action' => 'Documenti doganali',
        'dialog_title' => 'Crea documenti doganali',
        'required_hint' => 'Destinazione fuori dall’UE: per l’esportazione servono documenti doganali.',
        'eu_hint' => 'Destinazione nell’UE: di norma non servono documenti doganali. Fanno eccezione i territori fuori dal territorio doganale, come le Isole Canarie o Helgoland.',
        'reason' => 'Motivo della spedizione',
        'reason_hint' => 'Una vendita genera una fattura commerciale, altrimenti una fattura proforma. Il motivo viene salvato sulla consegna.',
        'submit' => 'Crea PDF',
        'commercial_invoice' => 'Fattura commerciale',
        'proforma_invoice' => 'Fattura proforma',
        'value' => 'Valore della merce (prezzo)',
        'reasons' => [
            'sale' => 'Vendita',
            'gift' => 'Regalo',
            'sample' => 'Campione commerciale',
            'documents' => 'Documenti',
            'returned_goods' => 'Merce resa',
            'repair' => 'Riparazione',
            'other' => 'Altro',
        ],
        'error' => [
            'no_customer' => 'La consegna non ha un destinatario.',
            'missing' => 'Per i documenti doganali di «:article» mancano: :fields.',
        ],
        'pdf' => [
            'date' => 'Data',
            'delivery_note' => 'Documento di trasporto',
            'invoice' => 'Fattura',
            'tracking' => 'Numero di tracciamento',
            'sender' => 'Mittente',
            'recipient' => 'Destinatario',
            'vat_id' => 'Partita IVA',
            'eori' => 'Numero EORI',
            'reason' => 'Motivo della spedizione',
            'currency' => 'Valuta',
            'col' => [
                'description' => 'Descrizione della merce',
                'tariff' => 'Codice doganale',
                'origin' => 'Paese d’origine',
                'quantity' => 'Quantità',
                'net_weight' => 'Peso netto (kg)',
                'unit_value' => 'Valore unitario',
                'total_value' => 'Valore totale',
            ],
            'total_net_weight' => 'Peso netto totale',
            'gross_weight' => 'Peso lordo',
            'parcels' => 'Colli',
            'total_value' => 'Valore totale',
            'no_sale' => 'Nessuna vendita: il valore è indicato solo a fini doganali.',
            'declaration' => 'Dichiariamo che le informazioni contenute in questo documento sono corrette e complete.',
            'place_date' => 'Luogo, data',
            'signature' => 'Firma',
        ],
    ],
];
