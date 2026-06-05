<?php

return [
    'tabs' => [
        'pagination' => 'Listes',
        'invoicing' => 'Facturation',
        'uploads' => 'Téléversements',
        'validation' => 'Limites de saisie',
        'notifications' => 'Notifications',
        'ui' => 'Interface',
        'routing' => 'Routage et cartes',
    ],
    'hint' => 'Laisser vide pour utiliser la valeur par défaut du système.',
    'pagination' => [
        'heading' => 'Tailles de page',
        'description' => 'Nombre d\'éléments par page dans les listes.',
        'timesheets' => 'Feuilles de temps',
        'duty_plans' => 'Plannings de service',
        'customers' => 'Clients',
        'customer_search' => 'Recherche de clients (saisie semi-automatique)',
        'customer_attachments' => 'Pièces jointes client',
        'organizations' => 'Organisations',
        'tours' => 'Tournées',
        'vehicles' => 'Véhicules',
        'tags' => 'Tags',
        'archive' => 'Archive',
        'dashboard_recent' => 'Tableau de bord : éléments récents',
    ],
    'invoicing' => [
        'heading' => 'Valeurs par défaut de facturation',
        'description' => 'Valeurs pré-remplies lors de la création d\'une nouvelle facture.',
        'default_tax_rate' => 'Taux de TVA par défaut (%)',
        'default_currency' => 'Devise par défaut (ISO-4217)',
        'time_unit' => 'Unité de temps pour les positions',
    ],
    'uploads' => [
        'heading' => 'Limites de taille de téléversement (Ko)',
        'description' => 'Tailles maximales de téléversement, en kilo-octets.',
        'csv_import_kb' => 'Import CSV',
        'customer_attachment_kb' => 'Pièce jointe client',
    ],
    'validation' => [
        'heading' => 'Limites de saisie',
        'description' => 'Limites de caractères et de plage pour les champs de formulaire.',
        'attendance' => [
            'heading' => 'Présence',
            'note_max' => 'Note, caractères max',
            'device_max' => 'ID d\'appareil, caractères max',
            'break_minutes_max' => 'Pause, minutes max',
        ],
        'tag' => [
            'heading' => 'Tags',
            'name_max' => 'Nom de tag, caractères max',
        ],
        'comment' => [
            'heading' => 'Commentaires',
            'body_max' => 'Corps du commentaire, caractères max',
        ],
        'duty_plan' => [
            'heading' => 'Plannings de service',
            'note_max' => 'Note, caractères max',
        ],
    ],
    'notifications' => [
        'heading' => 'Notifications push',
        'description' => 'Comportement des messages push.',
        'push' => [
            'body_truncate' => 'Aperçu du message, caractères max',
        ],
    ],
    'ui' => [
        'heading' => 'Comportement de l\'interface',
        'description' => 'Comportement visuel et interactif de l\'interface.',
        'calendar' => [
            'heading' => 'Calendrier',
            'slot_minutes' => 'Durée des créneaux en minutes',
        ],
        'dashboard' => [
            'heading' => 'Tableau de bord',
            'recent_limit' => 'Nombre d\'éléments récents',
        ],
        'search' => [
            'heading' => 'Recherche',
            'results_limit' => 'Limite de résultats par défaut',
        ],
    ],
    'reset' => 'Réinitialiser par défaut',
    'placeholder_default' => 'Par défaut :value',
    'routing' => [
        'nominatim' => [
            'heading' => 'Nominatim (géocodage)',
            'base_url' => 'URL de base',
            'email' => 'E-mail de contact',
            'rate_limit_per_sec' => 'Requêtes par seconde',
        ],
        'osrm' => [
            'heading' => 'OSRM (routage)',
            'base_url' => 'URL de base',
            'profile' => 'Profil (p. ex. driving)',
            'timeout' => 'Délai d\'attente (secondes)',
        ],
        'tiles' => [
            'heading' => 'Tuiles de carte',
            'url' => 'Modèle d\'URL de tuile',
            'max_zoom' => 'Zoom maximum',
        ],
    ],
];
