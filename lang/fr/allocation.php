<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : allocation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Répartir le temps',
    'entry_duration' => 'Durée de la saisie',
    'hint' => 'Les lignes vides sont ignorées ; vider toutes les lignes supprime la répartition. La somme des parts ne doit pas dépasser la durée.',
    'target' => 'Cible',
    'minutes' => 'Minutes',
    'quantity' => 'Quantité',
    'comment' => 'Commentaire',
    'none_option' => '— aucune part —',
    'type' => [
        'task' => 'Tâches',
        'asset' => 'Actifs',
        'project' => 'Projets',
        'cost_center' => 'Centres de coûts',
        'site' => 'Sites',
        'vehicle' => 'Véhicules',
        'activity_category' => 'Activités',
    ],
    'action' => [
        'split' => 'Répartir',
        'save' => 'Enregistrer la répartition',
    ],
    'flash' => [
        'saved' => 'Répartition enregistrée.',
    ],
    'error' => [
        'locked' => 'La saisie est verrouillée (:reason) — répartition impossible.',
        'invalid_target' => 'Cible de répartition invalide ou étrangère.',
        'minutes_min' => 'Chaque part nécessite au moins une minute.',
        'sum_exceeds' => 'La somme des parts (:sum min) dépasse la durée de la saisie (:max min).',
    ],
    // Dimensions libres du mandant (MVP-514 P2)
    'dimensions' => [
        'nav' => 'Dimensions de temps',
        'title' => 'Dimensions de temps libres',
        'intro' => 'Dimensions personnalisées pour la répartition du temps (p. ex. commandes ERP) — uniquement pour des cibles sans modèle WorkDiary existant. L\'ID externe ancre une future synchronisation par fournisseur.',
        'new_type' => 'Nouveau type de dimension',
        'code' => 'Code',
        'name' => 'Nom',
        'create_type' => 'Créer le type',
        'enabled' => 'Actif',
        'disabled' => 'Inactif',
        'no_types' => 'Aucun type de dimension pour l\'instant.',
        'no_values' => 'Aucune valeur pour l\'instant.',
        'external_id' => 'ID externe',
        'validity' => 'Validité',
        'valid_from' => 'Valable à partir du',
        'valid_until' => 'Valable jusqu\'au',
        'create_value' => 'Créer la valeur',
        'delete_value' => 'Supprimer',
        'flash' => [
            'type_created' => 'Type de dimension créé.',
            'type_enabled' => 'Type de dimension activé.',
            'type_disabled' => 'Type de dimension désactivé.',
            'value_created' => 'Valeur créée.',
            'value_deleted' => 'Valeur supprimée.',
        ],
    ],
];
