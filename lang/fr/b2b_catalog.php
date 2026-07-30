<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : b2b_catalog.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Accès catalogue B2B (fonctionnalité 099) : punchout OCI sortant + réception de commandes openTRANS.
return [
    'title' => 'Accès catalogue B2B',
    'intro' => 'Les systèmes d\'achat de vos clients B2B accèdent au catalogue d\'articles validé via OCI 4.0 et renvoient leurs commandes au format openTRANS 2.1 ORDER.',
    'punchout_url' => 'URL punchout (pour le système d\'achat du client)',

    'access_new_heading' => 'Créer un nouvel accès',
    'access_new_hint' => 'Un accès par client : nom d\'utilisateur + secret pour le punchout OCI. Le secret n\'est affiché qu\'une seule fois.',
    'access_heading' => 'Accès punchout',
    'access_empty' => 'Aucun accès créé pour le moment.',
    'access_title' => 'Accès : :label',

    'new_secret_heading' => 'Nouveau secret punchout',
    'new_secret_hint' => 'Copiez-le maintenant et enregistrez-le dans le système d\'achat du client — le texte en clair n\'est affiché que cette seule fois.',

    'items_heading' => 'Articles validés',
    'items_hint' => 'Seuls les articles explicitement validés sont visibles dans le punchout. Sans prix client, le prix de vente standard s\'applique.',
    'items_empty' => 'Aucun article validé pour le moment.',

    'orders_heading' => 'Commandes openTRANS',
    'orders_hint' => 'Les commandes (téléversement, e-mail ou cloud) apparaissent comme propositions dans la boîte d\'attribution ; seule la comptabilisation crée la commande.',
    'orders_empty' => 'Aucune commande reçue pour le moment.',

    'field' => [
        'customer' => 'Client',
        'customer_placeholder' => '… choisir un client',
        'label' => 'Libellé',
        'username' => 'Nom d\'utilisateur',
        'items_count' => 'Articles',
        'last_used' => 'Dernière utilisation',
        'status' => 'Statut',
        'actions' => 'Actions',
        'article' => 'Article',
        'article_placeholder' => '… choisir un article',
        'article_number' => 'N° d\'article',
        'article_name' => 'Article',
        'default_price' => 'Prix standard',
        'custom_price' => 'Prix client',
        'custom_price_placeholder' => 'Standard',
        'order_id' => 'N° de commande',
        'source' => 'Canal',
        'total_net' => 'Total net',
        'ordered_at' => 'Date de commande',
    ],

    'action' => [
        'issue' => 'Créer l\'accès',
        'manage' => 'Gérer',
        'revoke' => 'Désactiver',
        'rotate' => 'Renouveler le secret',
        'back' => 'Retour à l\'aperçu',
        'release' => 'Valider l\'article',
        'remove' => 'Retirer',
        'upload_order' => 'Téléverser une commande',
    ],

    'status' => [
        'active' => 'Actif',
        'revoked' => 'Désactivé',
        'order_open' => 'Ouverte (boîte)',
        'order_booked' => 'Comptabilisée',
        'order_dismissed' => 'Rejetée',
    ],

    'flash' => [
        'access_issued' => 'Accès créé.',
        'access_rotated' => 'Secret renouvelé.',
        'access_revoked' => 'Accès désactivé.',
        'item_released' => 'Article validé.',
        'item_removed' => 'Validation retirée.',
        'order_received' => 'Commande :id reçue — une proposition attend dans la boîte d\'attribution.',
        'order_duplicate' => 'La commande :id est déjà enregistrée (aucun changement).',
    ],

    'error' => [
        'not_opentrans' => 'Le fichier n\'est pas un openTRANS 2.1 ORDER lisible : :reason',
        'customer_required' => 'Veuillez choisir un client.',
        'not_open' => 'La commande n\'est plus ouverte.',
    ],

    'order' => [
        'entry_title' => 'Commande :id',
        'entry_intro' => 'Commande openTRANS :id (canal : :source).',
        'line_unmatched' => 'article non attribué',
    ],

    'public' => [
        'title' => 'Catalogue B2B',
        'footer' => 'Catalogue punchout — le panier est transmis à votre système d\'achat ; la commande passe par votre propre système.',
        'search_placeholder' => 'N° d\'article ou libellé …',
        'search' => 'Rechercher',
        'empty' => 'Aucun article validé trouvé.',
        'col_number' => 'N° d\'article',
        'col_name' => 'Libellé',
        'col_unit' => 'Unité',
        'col_price' => 'Prix',
        'col_quantity' => 'Quantité',
        'page_of' => 'Page :current sur :last',
        'prev' => 'Précédent',
        'next' => 'Suivant',
        'to_cart' => 'Transmettre le panier',
        'transfer_title' => 'Transmission au système d\'achat',
        'transfer_hint' => 'Le panier est transmis à votre système d\'achat. Si la redirection ne démarre pas automatiquement, utilisez le bouton.',
        'transfer_submit' => 'Transmettre le panier maintenant',
        'error_title' => 'Accès catalogue',
        'error_hook_url' => 'HOOK_URL invalide — seules les adresses HTTPS sont autorisées.',
        'error_credentials' => 'Identifiants invalides ou accès désactivé.',
        'error_session' => 'La session catalogue a expiré. Veuillez relancer le punchout depuis votre système d\'achat.',
        'error_empty_cart' => 'Aucune position avec quantité sélectionnée.',
    ],
];
