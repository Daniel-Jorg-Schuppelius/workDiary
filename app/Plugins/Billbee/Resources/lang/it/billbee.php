<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : billbee.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Billbee',
    'intro' => 'Ordini multicanale da Billbee (Amazon, eBay, Otto, Kaufland, Shopify …) — importazione inbox-first, i clienti non vengono mai assegnati alla cieca. Credenziali: scheda del plugin.',
    'field' => [
        'channel' => 'Canale',
        'state' => 'Stato',
        'order_number' => 'N. ordine',
        'buyer' => 'Acquirente',
        'customer' => 'Cliente',
        'total' => 'Lordo',
        'ordered_at' => 'Ordinato il',
    ],
    'filter' => [
        'all_channels' => 'Tutti i canali',
        'apply' => 'Filtra',
        'search' => 'Ordine o acquirente',
    ],
    'action' => [
        'sync' => 'Sincronizza ora',
    ],
    'flash' => [
        'synced' => 'Sincronizzazione Billbee completata: :imported nuovi, :staged assegnazioni aperte.',
        'sync_failed' => 'Sincronizzazione Billbee non riuscita — dettagli nel log.',
    ],
    'open_inbox' => ':count assegnazioni aperte',
    'last_sync' => 'Ultima sincronizzazione :at',
    'status' => [
        'open_assignment' => 'Assegnazione aperta',
    ],
    'empty' => 'Nessun ordine ancora replicato.',
    // Bereich der globalen Suche (Feature 181).
    'search' => [
        'domain' => 'Ordini Billbee',
    ],
    // Panel am Kunden-Cockpit (MVP-1370).
    'cockpit' => [
        'title' => 'Ordini Billbee',
    ],
    // Bestandsführung (MVP-1626)
    'inventory' => [
        'heading' => 'Gestione delle giacenze',
        'explain' => 'Con «Billbee», WorkDiary continua a registrare nel proprio giornale di magazzino e comunica ogni livello come giacenza assoluta per SKU a Billbee, che la distribuisce ai marketplace. «Locale» non comunica nulla. Non esiste una modalità di sola lettura: Billbee non tiene giacenze proprie.',
        'mode_local' => 'Locale — WorkDiary gestisce le giacenze, Billbee non riceve nulla.',
        'mode_external' => 'Billbee — WorkDiary gestisce le giacenze e comunica ogni livello a Billbee.',
        'save' => 'Salva gestione delle giacenze',
        'confirm' => 'Cambiare davvero la gestione delle giacenze?',
        'changed' => 'Gestione delle giacenze modificata.',
        'unchanged' => 'La gestione delle giacenze è già impostata così.',
        'needs_connection' => 'Mancano le credenziali per la comunicazione a Billbee nelle impostazioni del plugin.',
        'other_leads' => 'Le giacenze sono attualmente gestite da :plugin. Riportare prima lì la gestione su locale.',
    ],
];
