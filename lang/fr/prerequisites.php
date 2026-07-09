<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : prerequisites.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'blocked' => [
        'missing_required' => 'Prérequis manquant',
        'missing_optional' => 'Information',
        'not_licensed' => 'Non licencié',
        'not_allowed' => 'Aucune autorisation',
        'provider_unsupported' => 'Non pris en charge par le fournisseur',
    ],
    'contact_role' => 'Veuillez contacter : :role',
    'warehouses' => [
        'missing' => "Le comptage et la comptabilisation nécessitent au moins un entrepôt.",
        'cta' => 'Gérer les entrepôts',
    ],
    'dispatch' => [
        'cta' => "Vers le panneau de disposition de la commande",
    ],
    'mappings' => [
        'hint' => "Les correspondances sont créées automatiquement lors de l\'import ou de la résolution des éléments de la boîte de réception (synchronisation des plugins et import CSV).",
        'cta' => "Vers la boîte de réception des intégrations",
    ],
    'shift_types' => [
        'missing' => 'Aucun type de poste n\'a encore été créé — sans type, la planification des postes est limitée.',
        'cta' => 'Créer des types de poste',
        'dialog_hint' => 'Aucun type de poste disponible. Le poste est enregistré sans type ; l\'administration gère les types via « Types de poste » dans le planning.',
    ],
];
