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
    'title' => 'Expédition & logistique',
    'intro' => 'Connexions transporteur pour les étiquettes d\'expédition et le suivi des colis (DHL Paket, UPS, FedEx). Une connexion par transporteur et organisation ; les identifiants sont stockés chiffrés.',

    'form_heading' => 'Ajouter / modifier une connexion',
    'form_hint' => 'Choisissez le transporteur et saisissez ses identifiants. Enregistrer à nouveau avec le même transporteur met à jour la connexion existante.',
    'secret_hint' => 'Le mot de passe et la clé API sont stockés chiffrés et ne sont plus jamais affichés. Laissez-les vides lors de la modification pour conserver les valeurs enregistrées.',
    'connections_heading' => 'Connexions existantes',
    'no_connections' => 'Aucune connexion transporteur configurée pour le moment.',

    'field' => [
        'carrier' => 'Transporteur',
        'name' => 'Désignation',
        'username' => 'Utilisateur / ID client',
        'password' => 'Mot de passe / secret client',
        'api_key' => 'Clé API (DHL uniquement : dhl-api-key)',
        'billing_number' => 'Numéro de facturation / de compte',
        'sandbox' => 'Sandbox / environnement de test',
        'active' => 'Actif',
        'weight_grams' => 'Poids (g)',
        'length_cm' => 'Longueur (cm)',
        'width_cm' => 'Largeur (cm)',
        'height_cm' => 'Hauteur (cm)',
    ],

    'label_short' => 'Expédition',

    'col' => [
        'mode' => 'Mode',
        'status' => 'Statut',
    ],

    'mode' => [
        'sandbox' => 'Sandbox',
        'production' => 'Production',
    ],

    'status_label' => [
        'active' => 'Actif',
        'inactive' => 'Inactif',
    ],

    'action' => [
        'save' => 'Enregistrer',
        'disconnect' => 'Désactiver',
        'create' => 'Expédier',
    ],

    'flash' => [
        'saved' => 'Connexion transporteur enregistrée.',
        'disconnected' => 'Connexion transporteur désactivée.',
        'credentials_required' => 'Une nouvelle connexion requiert l\'utilisateur/ID client et le mot de passe/secret client (DHL en plus : clé API).',
        'no_recipient' => 'La livraison n\'a pas de client comme destinataire.',
        'already_created' => 'Une expédition existe déjà pour cette livraison.',
        'no_connection' => 'Aucune connexion active n\'est configurée pour le transporteur sélectionné.',
        'label_created' => 'Expédition créée et étiquette récupérée.',
        'label_failed' => 'Impossible de créer l\'étiquette d\'expédition : :reason',
    ],

    'notify' => [
        'delivery_problem' => [
            'title' => 'Problème de livraison d\'une expédition',
            'message' => 'L\'expédition :tracking (:carrier) signale un problème de livraison.',
        ],
    ],

    // Statut d'expédition (ShipmentStatus).
    'status' => [
        'draft' => 'Brouillon',
        'labeled' => 'Étiquette créée',
        'in_transit' => 'En transit',
        'delivered' => 'Livré',
        'problem' => 'Problème de livraison',
        'cancelled' => 'Annulé',
    ],
];
