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
    'intro' => 'Connecte JTL-Wawi comme système de gestion des stocks maître : projection des articles et des entrepôts, lecture des stocks et transmission idempotente des écritures.',
    'beta_notice' => 'L’API JTL-Wawi est en programme bêta/pilote. Après la sortie officielle, la disponibilité peut dépendre de l’édition JTL souscrite et devenir payante.',

    'mode' => [
        'on_premise' => 'OnPremise',
        'cloud' => 'Passerelle cloud',
    ],

    'status' => [
        'draft' => 'Brouillon',
        'pending_registration' => 'Enregistrement en attente',
        'active' => 'Active',
        'blocked' => 'Bloquée',
        'disconnected' => 'Déconnectée',
    ],

    'field' => [
        'base_url' => 'URL de base de l’API Wawi',
        'base_url_help' => 'p. ex. https://wawi.example.local:5883/api/eazybusiness — l’instance API se crée dans l’administrateur JTL.',
        'api_version' => 'Version de l’API',
        'detected_version' => 'Version Wawi détectée',
        'company_id' => 'Société (x-companyid)',
        'company_id_help' => 'Facultatif : mandant/société au sein de la Wawi.',
        'tenant_id' => 'ID du tenant',
        'client_id' => 'ID client',
        'client_secret' => 'Secret client',
        'secret_keep' => '(inchangé — laisser vide)',
        'allow_private_network' => 'Autoriser explicitement les adresses privées/internes',
        'allow_private_network_help' => 'Une Wawi OnPremise se trouve généralement sur votre propre réseau. Cette autorisation est auditée et ne vaut que pour cette connexion.',
        'last_sync' => 'Dernière synchronisation',
        'last_error' => 'Dernière erreur',
    ],

    'stats' => [
        'linked_articles' => 'Articles associés',
        'open_inbox' => 'Cas d’association ouverts',
    ],

    'scopes' => [
        'missing' => 'Scopes de lecture manquants : :scopes — ajuster l’approbation de l’app dans JTL-Wawi puis revérifier l’enregistrement.',
        'missing_write' => 'Sans le scope d’écriture (:scopes), la transmission des stocks reste désactivée.',
    ],

    'registration' => [
        'heading' => 'Enregistrement de l’app',
        'explain' => 'Ouvrir « Admin > Enregistrement des apps » dans JTL-Wawi, puis lancer l’enregistrement ici. La clé API est délivrée une seule fois après approbation et stockée chiffrée.',
        'waiting' => 'L’enregistrement attend l’approbation dans JTL-Wawi. Après confirmation, vérifier le statut ici.',
    ],

    'connection' => [
        'heading' => 'Connexion',
    ],

    'sync' => [
        'section' => 'Section',
        'counters' => 'Compteurs',
        'warehouses' => 'Entrepôts',
        'articles' => 'Articles',
        'stocks' => 'Mouvements de stock',
    ],

    'warehouses' => [
        'heading' => 'Association des entrepôts',
        'empty' => 'Aucun entrepôt JTL projeté — synchroniser d’abord.',
        'jtl' => 'Entrepôt JTL',
        'type' => 'Type',
        'flags' => 'Attributs',
        'local' => 'Entrepôt WorkDiary',
        'inactive' => 'inactif',
        'lock_shipment' => 'Blocage expédition',
        'lock_availability' => 'Blocage disponibilité',
        'unmapped' => '— non associé —',
    ],

    'inventory' => [
        'heading' => 'Pilotage des stocks',
        'explain' => 'Définit quel système pilote les stocks. Le retour au mode « local » reprend les stocks JTL comme inventaire d’ouverture.',
        'mode_local' => 'Local — WorkDiary gère les stocks lui-même.',
        'mode_external' => 'Externe — JTL-Wawi pilote ; WorkDiary lit et transmet les écritures.',
        'mode_read_only' => 'Lecture seule — JTL-Wawi pilote ; WorkDiary affiche uniquement les stocks.',
    ],

    'action' => [
        'save' => 'Enregistrer',
        'sync_now' => 'Synchroniser maintenant',
        'disconnect' => 'Déconnecter',
        'start_registration' => 'Lancer l’enregistrement',
        'check_registration' => 'Vérifier l’approbation',
        'map' => 'Associer',
        'change_mode' => 'Changer de mode',
    ],

    'confirm' => [
        'disconnect' => 'Vraiment déconnecter ? Les associations et projections sont conservées, les identifiants sont supprimés.',
        'mode_change' => 'Vraiment changer le mode de pilotage des stocks ?',
    ],

    'flash' => [
        'saved' => 'Connexion enregistrée.',
        'cloud_connected' => 'Connexion cloud établie et jeton obtenu.',
        'cloud_failed' => 'Échec de la connexion cloud — vérifier les identifiants et l’ID du tenant.',
        'registration_started' => 'Enregistrement envoyé — l’approuver maintenant dans JTL-Wawi.',
        'registration_failed' => 'Échec de l’enregistrement.',
        'registration_pending' => 'L’approbation est encore en attente.',
        'registration_accepted' => 'Approuvé — clé API enregistrée.',
        'registration_rejected' => 'L’enregistrement a été refusé dans JTL-Wawi.',
        'not_active' => 'La connexion n’est pas active.',
        'sync_done' => 'Synchronisation terminée.',
        'sync_failed' => 'Échec de la synchronisation (:reason).',
        'warehouse_mapped' => 'Association d’entrepôt enregistrée.',
        'disconnected' => 'Connexion coupée.',
        'disconnect_blocked' => 'Déconnexion impossible : repasser d’abord le pilotage des stocks en « local ».',
        'mode_unchanged' => 'Ce mode est déjà actif.',
        'mode_needs_connection' => 'Le pilotage externe des stocks nécessite une connexion JTL active.',
        'mode_needs_mapping' => 'Le pilotage externe des stocks nécessite au moins un entrepôt JTL associé.',
        'mode_changed' => 'Mode de pilotage des stocks modifié.',
        'mode_changed_with_takeover' => 'Mode modifié — :booked corrections d’ouverture reprises de JTL.',
        'takeover_done' => 'Inventaire d’ouverture terminé : :booked corrections sur :pairs paires.',
        'takeover_failed' => 'Échec de l’inventaire d’ouverture (:reason).',
    ],
];
