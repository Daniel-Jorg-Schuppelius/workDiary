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
    'intro' => 'Commandes multicanales de Billbee (Amazon, eBay, Otto, Kaufland, Shopify …) — import inbox-first, jamais d\'affectation client à l\'aveugle. Identifiants : carte du plugin.',
    'field' => [
        'channel' => 'Canal',
        'state' => 'Statut',
        'order_number' => 'N° de commande',
        'buyer' => 'Acheteur',
        'customer' => 'Client',
        'total' => 'Brut',
        'ordered_at' => 'Commandé le',
    ],
    'filter' => [
        'all_channels' => 'Tous les canaux',
        'apply' => 'Filtrer',
    ],
    'action' => [
        'sync' => 'Synchroniser maintenant',
    ],
    'flash' => [
        'synced' => 'Synchronisation Billbee terminée : :imported nouvelles, :staged affectations ouvertes.',
        'sync_failed' => 'Échec de la synchronisation Billbee — voir le journal.',
    ],
    'open_inbox' => ':count affectations ouvertes',
    'last_sync' => 'Dernière synchronisation :at',
    'status' => [
        'open_assignment' => 'Affectation ouverte',
    ],
    'empty' => 'Aucune commande répliquée pour l\'instant.',
];
