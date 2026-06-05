<?php

return [
    'entity' => [
        'customers' => 'Clients',
        'projects' => 'Projets',
        'users' => 'Utilisateurs',
        'materials' => 'Matériaux',
        'scheduled_shifts' => 'Plannings de service',
        'tours' => 'Tournées',
        'remote_sessions' => 'Sessions de maintenance à distance',
    ],
    'state' => [
        'preflight' => 'Contrôle préalable',
        'awaitingApproval' => 'En attente d\'approbation',
        'running' => 'En cours',
        'succeeded' => 'Réussi',
        'partial' => 'Partiel',
        'failed' => 'Échoué',
    ],
    'errorCode' => [
        'required' => 'Champ obligatoire manquant',
        'format' => 'Erreur de format',
        'unique' => 'Valeur non unique',
        'fkMissing' => 'Référence introuvable',
        'tooLong' => 'Valeur trop longue',
        'outOfRange' => 'Valeur hors plage',
        'persist' => 'Erreur de persistance',
        'headerMissing' => 'Colonne manquante',
        'headerUnknown' => 'Colonne inconnue',
    ],
    'error' => [
        'required' => 'Le champ obligatoire :field est manquant.',
        'tooLong' => 'Le champ :field dépasse la longueur maximale de :max caractères.',
        'header' => [
            'missing' => 'La colonne requise :column est manquante dans l\'en-tête CSV.',
            'duplicate' => 'La colonne :column apparaît plusieurs fois.',
        ],
        'format' => [
            'default' => 'Le champ :field a un format invalide (:reason).',
            'email' => 'Adresse e-mail invalide.',
            'country' => 'Le code pays doit comporter 2 à 3 lettres majuscules (ISO 3166-1).',
            'currency' => 'Le code devise doit comporter 3 lettres majuscules (ISO 4217).',
            'enum' => 'La valeur n\'est pas un statut valide.',
            'parse' => 'Le fichier n\'a pas pu être analysé : :reason',
            'date' => 'Date invalide (attendu p. ex. « 28.05.2026, 09:42:09 »).',
            'time' => 'Heure invalide (attendu HH:MM).',
            'status' => 'La valeur n\'est pas un statut valide.',
        ],
        'outOfRange' => [
            'rowLimit' => 'Limite de lignes (:max) dépassée — reste ignoré.',
        ],
        'fkMissing' => [
            'customer' => 'Aucun client avec le numéro :number trouvé.',
            'user' => 'Aucun utilisateur avec l\'e-mail :value trouvé.',
        ],
        'persist' => [
            'noBookingUser' => 'Aucun utilisateur imputable trouvé dans l\'organisation.',
        ],
    ],
];
