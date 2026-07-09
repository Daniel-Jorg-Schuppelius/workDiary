<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : settingsregistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Paramètres (registre)',
        'subtitle' => 'Paramètres système et organisation enregistrés — avec valeur effective, origine et retour arrière.',
        'help_text' => 'Seules les clés déclarées dans le registre sont modifiables ici ; validation, sensibilité et audit sont définis par clé. Les valeurs d\'infrastructure (APP_KEY, base de données, transport mail) n\'apparaissent volontairement pas ici.',
    ],
    'scopes' => [
        'system' => 'Système (exploitant)',
        'organization' => 'Organisation',
        'user' => 'Utilisateur',
    ],
    'sources' => [
        'organization' => 'Substitution org',
        'system' => 'Substitution système',
        'config' => 'Fichier de configuration',
        'default' => 'Valeur par défaut',
    ],
    'field' => [
        'search' => 'Rechercher des clés…',
        'sensitive' => 'Sensible',
        'sensitive_placeholder' => 'Saisir une nouvelle valeur (valeur actuelle masquée)',
        'affects' => 'Concerne',
    ],
    'action' => [
        'save' => 'Enregistrer',
        'reset' => 'Rétablir la valeur par défaut',
        'history' => 'Historique',
    ],
    'empty' => [
        'title' => 'Aucun paramètre trouvé',
        'message' => 'Aucune clé de registre pour cette portée ou ce terme de recherche.',
        'history' => 'Aucune modification consignée pour le moment.',
    ],
    'flash' => [
        'saved' => 'Paramètre :key enregistré.',
        'reset' => 'Paramètre :key rétabli à la valeur par défaut.',
    ],
];
