<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : jtl_wawi.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'JTL-Wawi',
    'intro' => 'Collega JTL-Wawi come gestionale di magazzino principale: proiezione di articoli e magazzini, lettura delle giacenze e consegna idempotente delle registrazioni.',
    'beta_notice' => 'L’API JTL-Wawi è in programma beta/pilota. Dopo il rilascio ufficiale la disponibilità può dipendere dall’edizione JTL acquistata e diventare a pagamento.',

    'mode' => [
        'on_premise' => 'OnPremise',
        'cloud' => 'Gateway cloud',
    ],

    'status' => [
        'draft' => 'Bozza',
        'pending_registration' => 'Registrazione in attesa',
        'active' => 'Attiva',
        'blocked' => 'Bloccata',
        'disconnected' => 'Disconnessa',
    ],

    'field' => [
        'base_url' => 'URL di base dell’API Wawi',
        'base_url_help' => 'es. https://wawi.example.local:5883/api/eazybusiness — l’istanza API si crea nell’amministratore JTL.',
        'api_version' => 'Versione API',
        'detected_version' => 'Versione Wawi rilevata',
        'company_id' => 'Azienda (x-companyid)',
        'company_id_help' => 'Facoltativo: mandante/azienda all’interno della Wawi.',
        'tenant_id' => 'ID tenant',
        'client_id' => 'ID client',
        'client_secret' => 'Segreto client',
        'secret_keep' => '(invariato — lasciare vuoto)',
        'allow_private_network' => 'Consentire esplicitamente indirizzi privati/interni',
        'allow_private_network_help' => 'Una Wawi OnPremise si trova di solito nella propria rete. Questa autorizzazione è auditata e vale solo per questa connessione.',
        'last_sync' => 'Ultima sincronizzazione',
        'last_error' => 'Ultimo errore',
    ],

    'stats' => [
        'linked_articles' => 'Articoli associati',
        'open_inbox' => 'Casi di associazione aperti',
    ],

    'scopes' => [
        'missing' => 'Scope di lettura mancanti: :scopes — adeguare l’approvazione dell’app in JTL-Wawi e ricontrollare la registrazione.',
        'missing_write' => 'Senza lo scope di scrittura (:scopes) la consegna delle giacenze resta disattivata.',
    ],

    'registration' => [
        'heading' => 'Registrazione dell’app',
        'explain' => 'Aprire in JTL-Wawi «Admin > Registrazione app», poi avviare qui la registrazione. La chiave API viene emessa una sola volta dopo l’approvazione e salvata cifrata.',
        'waiting' => 'La registrazione attende l’approvazione in JTL-Wawi. Dopo la conferma, verificare qui lo stato.',
    ],

    'connection' => [
        'heading' => 'Connessione',
    ],

    'sync' => [
        'section' => 'Sezione',
        'counters' => 'Contatori',
        'warehouses' => 'Magazzini',
        'articles' => 'Articoli',
        'stocks' => 'Variazioni di giacenza',
    ],

    'warehouses' => [
        'heading' => 'Associazione magazzini',
        'empty' => 'Nessun magazzino JTL proiettato — sincronizzare prima.',
        'jtl' => 'Magazzino JTL',
        'type' => 'Tipo',
        'flags' => 'Attributi',
        'local' => 'Magazzino WorkDiary',
        'inactive' => 'inattivo',
        'lock_shipment' => 'Blocco spedizione',
        'lock_availability' => 'Blocco disponibilità',
        'unmapped' => '— non associato —',
    ],

    'inventory' => [
        'heading' => 'Guida delle giacenze',
        'explain' => 'Definisce quale sistema guida le giacenze. Il ritorno a «locale» importa le giacenze JTL come inventario di apertura.',
        'mode_local' => 'Locale — WorkDiary gestisce le giacenze da sé.',
        'mode_external' => 'Esterno — guida JTL-Wawi; WorkDiary legge e consegna le registrazioni.',
        'mode_read_only' => 'Sola lettura — guida JTL-Wawi; WorkDiary mostra soltanto le giacenze.',
    ],

    'action' => [
        'save' => 'Salva',
        'sync_now' => 'Sincronizza ora',
        'disconnect' => 'Disconnetti',
        'start_registration' => 'Avvia registrazione',
        'check_registration' => 'Verifica approvazione',
        'map' => 'Associa',
        'change_mode' => 'Cambia modalità',
    ],

    'confirm' => [
        'disconnect' => 'Disconnettere davvero? Associazioni e proiezioni restano, le credenziali vengono eliminate.',
        'mode_change' => 'Cambiare davvero la modalità di guida delle giacenze?',
    ],

    'flash' => [
        'saved' => 'Connessione salvata.',
        'cloud_connected' => 'Connessione cloud stabilita e token ottenuto.',
        'cloud_failed' => 'Connessione cloud non riuscita — verificare credenziali e ID tenant.',
        'registration_started' => 'Registrazione inviata — approvarla ora in JTL-Wawi.',
        'registration_failed' => 'Registrazione non riuscita.',
        'registration_pending' => 'L’approvazione è ancora in sospeso.',
        'registration_accepted' => 'Approvata — chiave API salvata.',
        'registration_rejected' => 'La registrazione è stata rifiutata in JTL-Wawi.',
        'not_active' => 'La connessione non è attiva.',
        'sync_done' => 'Sincronizzazione completata.',
        'sync_failed' => 'Sincronizzazione non riuscita (:reason).',
        'warehouse_mapped' => 'Associazione del magazzino salvata.',
        'disconnected' => 'Connessione interrotta.',
        'disconnect_blocked' => 'Disconnessione non possibile: impostare prima la guida delle giacenze su «locale».',
        'mode_unchanged' => 'Questa modalità è già attiva.',
        'mode_needs_connection' => 'La guida esterna delle giacenze richiede una connessione JTL attiva.',
        'mode_needs_mapping' => 'La guida esterna delle giacenze richiede almeno un magazzino JTL associato.',
        'mode_changed' => 'Modalità di guida delle giacenze cambiata.',
        'mode_changed_with_takeover' => 'Modalità cambiata — :booked rettifiche di apertura importate da JTL.',
        'takeover_done' => 'Inventario di apertura completato: :booked rettifiche su :pairs coppie.',
        'takeover_failed' => 'Inventario di apertura non riuscito (:reason).',
    ],
];
