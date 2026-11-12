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
        'search' => 'Commande ou acheteur',
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
    // Bereich der globalen Suche (Feature 181).
    'search' => [
        'domain' => 'Commandes Billbee',
    ],
    // Panel am Kunden-Cockpit (MVP-1370).
    'cockpit' => [
        'title' => 'Commandes Billbee',
    ],
    // Bestandsführung (MVP-1626)
    'inventory' => [
        'heading' => 'Gestion des stocks',
        'explain' => 'Avec « Billbee », WorkDiary continue de comptabiliser dans son propre journal de stock et transmet chaque niveau comme stock absolu par SKU à Billbee, qui le répartit sur les places de marché. « Local » ne transmet rien. Il n’existe pas de mode lecture seule : Billbee ne tient pas de stock propre.',
        'mode_local' => 'Local — WorkDiary gère les stocks, Billbee ne reçoit rien.',
        'mode_external' => 'Billbee — WorkDiary gère les stocks et transmet chaque niveau à Billbee.',
        'save' => 'Enregistrer la gestion des stocks',
        'confirm' => 'Vraiment changer la gestion des stocks ?',
        'changed' => 'Gestion des stocks modifiée.',
        'unchanged' => 'La gestion des stocks est déjà réglée ainsi.',
        'needs_connection' => 'Les identifiants pour la transmission à Billbee manquent dans les paramètres du plugin.',
        'other_leads' => 'Le stock est actuellement piloté par :plugin. Repassez-y d’abord la gestion des stocks en local.',
    ],
];
