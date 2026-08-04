<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : etsy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Etsy',
    'intro' => 'Connexion directe de la boutique Etsy (Open API v3) — les commandes arrivent inbox-first dans le miroir, l\'affectation client n\'est jamais aveugle. Identifiants de la seller app propre à l\'organisation : carte du plugin.',
    'connection' => [
        'active' => 'Connecté : :shop',
        'none' => 'Non connecté',
        'connect' => 'Connecter à Etsy',
        'disconnect' => 'Déconnecter',
        'disconnect_confirm' => 'Vraiment déconnecter Etsy ? Les lignes miroir et les affectations sont conservées.',
        'shop_pending' => 'Recherche de boutique en attente',
        'shop_conflict' => 'Cette boutique Etsy est déjà connectée à une autre organisation.',
        'not_configured' => 'Enregistrez d\'abord keystring/shared secret sur la carte du plugin.',
    ],
    'setup' => [
        'callback' => 'Redirect URI de la seller app (exacte, HTTPS) :',
        'webhook' => 'URL de webhook pour le portail Etsy (événements order.*) :',
    ],
    'field' => [
        'receipt' => 'Commande',
        'status' => 'Statut',
        'buyer' => 'Acheteur',
        'customer' => 'Client',
        'total' => 'Brut',
        'ordered_at' => 'Commandé le',
        'shipping' => 'Expédition',
        'tracking_code' => 'N° de suivi',
        'carrier' => 'Transporteur',
    ],
    'filter' => [
        'all_statuses' => 'Tous les statuts',
        'apply' => 'Filtrer',
    ],
    'action' => [
        'sync' => 'Synchroniser maintenant',
        'ship' => 'Déclarer l\'expédition',
        'ship_submit' => 'Déclarer',
    ],
    'status' => [
        'open_assignment' => 'Affectation ouverte',
        'guest' => 'Commande invité',
        'shipped' => 'Expédié',
    ],
    'flash' => [
        'synced' => 'Synchronisation Etsy terminée : :imported nouveaux, :staged affectations ouvertes.',
        'sync_failed' => 'Échec de la synchronisation Etsy — détails dans le journal.',
        'already_shipped' => 'La commande est déjà déclarée comme expédiée.',
        'ship_queued' => 'Déclaration d\'expédition en file — Etsy sera notifié.',
    ],
    'ledger' => [
        'caption' => 'Frais et versements des 90 derniers jours (ledger de paiement Etsy).',
        'type' => 'Type',
        'amount' => 'Somme',
        'entries' => 'Entrées',
    ],
    'open_inbox' => ':count affectations ouvertes',
    'last_sync' => 'Dernière synchronisation :at',
    'empty' => 'Aucune commande en miroir pour le moment.',
];
