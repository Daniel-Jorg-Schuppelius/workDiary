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
    ],
    'action' => [
        'sync' => 'Sincronizza ora',
    ],
    'flash' => [
        'synced' => 'Sincronizzazione Billbee completata: :imported nuovi, :staged assegnazioni aperte.',
        'sync_failed' => 'Sincronizzazione Billbee non riuscita — vedi il log.',
    ],
    'open_inbox' => ':count assegnazioni aperte',
    'last_sync' => 'Ultima sincronizzazione :at',
    'status' => [
        'open_assignment' => 'Assegnazione aperta',
    ],
    'empty' => 'Nessun ordine ancora replicato.',
];
