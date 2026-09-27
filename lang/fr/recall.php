<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : recall.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Rückrufaktionen (MVP-921/922).
return [
    'title' => 'Rappels de produits',
    'nav' => 'Rappels',
    'subtitle' => 'Rappel par variante d\'article : identifier les livraisons et clients concernés, bloquer le stock, suivre l\'état par client.',
    'empty' => 'Aucun rappel.',
    'items_none' => 'Aucune livraison concernée.',
    'yes' => 'oui',
    'no' => 'non',
    'kpi' => [
        'active' => 'Rappels actifs',
    ],
    'filter' => [
        'all_status' => 'Tous les statuts',
    ],
    'field' => [
        'number' => 'Numéro',
        'title' => 'Intitulé',
        'variant' => 'Variante d\'article',
        'kind' => 'Motif',
        'open_items' => 'Ouverts / concernés',
        'status' => 'Statut',
        'reason' => 'Motif et mesure',
        'customer_message' => 'Message aux clients',
        'manufacturing_orders' => 'Ordres de fabrication',
        'delivered_from' => 'Livré à partir du',
        'delivered_until' => 'Livré jusqu\'au',
        'serial_numbers' => 'Numéros de série',
        'is_blocking_stock' => 'Bloquer le stock du périmètre',
        'activated_at' => 'Activé le',
        'delivered_at' => 'Livré le',
        'customer' => 'Client',
        'quantity' => 'Quantité',
        'serial' => 'Numéro de série',
        'actions' => 'Actions',
        'claim' => 'Réclamation',
        'sent_at' => 'Envoyé le',
        'recipient' => 'Destinataire',
    ],
    'hint' => [
        'customer_message' => 'Utilisé dans le portail client et dans le courrier.',
        'scope' => 'Les champs vides ne restreignent pas ; tous les champs renseignés s\'appliquent ensemble.',
        'list' => 'Séparés par des virgules ou un par ligne.',
        'claim' => 'Ouvrir une réclamation avec RMA pour le retour.',
    ],
    'section' => [
        'recall' => 'Rappel',
        'scope' => 'Périmètre',
        'preview' => 'Aperçu des livraisons concernées',
        'items' => 'Livraisons concernées',
        'dispatches' => 'Justificatifs d\'envoi',
    ],
    'preview' => [
        'summary' => ':deliveries livraisons concernées, :stock numéros de série en stock',
        'none' => 'Aucune livraison dans ce périmètre.',
    ],
    'action' => [
        'create' => 'Créer un rappel',
        'show' => 'Afficher',
        'edit' => 'Modifier',
        'save' => 'Enregistrer',
        'notify' => 'Informer les clients',
        'claim' => 'Réclamation',
    ],
    'dialog' => [
        'create' => 'Créer un rappel',
        'edit' => 'Modifier le rappel',
    ],
    'transition' => [
        'active' => 'Activer',
        'completed' => 'Clôturer',
        'cancelled' => 'Annuler',
    ],
    'confirm' => [
        'active' => 'Activer le rappel ? Les livraisons concernées sont figées et le stock du périmètre est bloqué.',
        'completed' => 'Clôturer le rappel ?',
        'cancelled' => 'Annuler le rappel ? Les blocages de ce rappel sont levés.',
        'notify' => 'Envoyer un e-mail à tous les clients ayant des positions ouvertes ?',
    ],
    'item_transition' => [
        'notified' => 'Informé',
        'returned' => 'Retourné',
        'resolved' => 'Réglé',
    ],
    'status' => [
        'draft' => 'Brouillon',
        'active' => 'Actif',
        'completed' => 'Clôturé',
        'cancelled' => 'Annulé',
    ],
    'item_status' => [
        'open' => 'Ouvert',
        'notified' => 'Informé',
        'returned' => 'Retourné',
        'resolved' => 'Réglé',
    ],
    'kind' => [
        'safety' => 'Sécurité',
        'quality' => 'Qualité',
        'regulatory' => 'Exigence réglementaire',
    ],
    'error' => [
        'not_draft' => 'Seuls les brouillons peuvent être modifiés.',
        'not_active' => 'Les courriers ne sont possibles que pour les rappels actifs.',
    ],
    'flash' => [
        'created' => 'Rappel :number créé.',
        'saved' => 'Rappel enregistré.',
        'status' => 'Statut : :status.',
        'item' => 'État enregistré.',
        'notified' => ':count clients informés.',
        'without_email' => 'Sans e-mail valide, veuillez informer autrement : :customers',
        'claim' => 'Réclamation :number ouverte pour le retour.',
    ],
    'stats' => [
        'return_rate' => 'Taux de retour',
    ],
    'dispatch' => [
        'queued' => 'En file d\'attente',
        'sent' => 'Envoyé',
        'failed' => 'Échec',
        'none' => 'Aucun courrier envoyé pour l\'instant.',
    ],
    'mail' => [
        'subject' => 'Rappel : :title (:number)',
        'body' => "Bonjour :name,\n\nnous rappelons le produit suivant : :product.\n\n:message",
        'default_message' => 'Veuillez cesser d\'utiliser le produit et nous contacter ; nous organiserons le retour ou l\'échange.',
        'serials' => 'Numéros de série concernés : :serials',
    ],
    'claim' => [
        'title' => 'Rappel :number : :title',
    ],
    'portal' => [
        'subject' => 'Rappel : :title (:product)',
    ],
    // Behördenmeldung (MVP-945).
    'authority' => [
        'title' => 'Notification aux autorités',
        'save' => 'Enregistrer',
        'pdf' => 'Formulaire de notification',
        'pdf_title' => 'Formulaire de rappel',
        'pdf_note' => 'Synthèse des informations pour la notification à la surveillance du marché ; la notification elle-même se fait sur le portail de l\'autorité compétente.',
        'section' => [
            'product' => 'Produit',
            'hazard' => 'Danger et mesure',
            'scope' => 'Étendue',
            'authority' => 'Autorité',
        ],
        'field' => [
            'product' => 'Produit',
            'gtin' => 'GTIN',
            'batches' => 'Numéros de série',
            'delivered' => 'Période de livraison',
            'hazard_kind' => 'Type de danger',
            'hazard_description' => 'Description du danger',
            'risk_level' => 'Niveau de risque',
            'measure' => 'Mesure',
            'countries' => 'Pays de distribution',
            'units' => 'Unités concernées',
            'customers' => 'Clients concernés',
            'returned' => 'Retours',
            'activated_at' => 'Rappel depuis',
            'authority_name' => 'Autorité',
            'authority_reference' => 'Référence',
            'authority_reported_on' => 'Notifié le',
            'contact' => 'Contact',
            'contact_name' => 'Personne de contact',
            'contact_email' => 'E-mail du contact',
        ],
        'hint' => [
            'hazard_kind' => 'p. ex. incendie, électrocution, blessure, chimique',
            'countries' => 'Codes pays séparés par des virgules (DE, AT, …)',
        ],
        'risk' => [
            'low' => 'faible',
            'medium' => 'moyen',
            'high' => 'élevé',
            'serious' => 'grave',
        ],
        'measure' => [
            'withdrawal' => 'Retrait du marché',
            'recall' => 'Rappel auprès des utilisateurs',
            'warning' => 'Avertissement',
            'destruction' => 'Destruction',
        ],
        'flash' => [
            'saved' => 'Informations enregistrées.',
        ],
    ],
];
