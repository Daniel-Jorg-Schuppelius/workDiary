<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : training.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'section' => 'Formations',
    'nav' => [
        'courses' => 'Catalogue de formations',
        'requirements' => 'Matrice des obligations',
        'assignments' => 'Plan de formation',
    ],
    'title' => [
        'courses' => 'Catalogue de formations',
        'requirements' => 'Matrice des obligations',
        'assignments' => 'Plan de formation',
    ],
    'subtitle' => [
        'courses' => 'Formations avec organisme, durée, validité et base légale — les preuves restent dans le registre de sécurité.',
        'requirements' => 'Quel rôle ou domaine d’activité doit quelle formation ; le plan par personne en découle.',
        'assignments' => 'Qui doit quelle formation et pour quand — avec la preuve issue de l’instruction.',
    ],

    'field' => [
        'code' => 'Code de formation',
        'title' => 'Intitulé',
        'provider_kind' => 'Organisme',
        'provider_name' => 'Nom de l’organisme',
        'duration_minutes' => 'Durée (minutes)',
        'validity_months' => 'Validité (mois)',
        'is_mandatory' => 'Formation obligatoire',
        'legal_basis' => 'Base légale',
        'cost' => 'Coût',
        'cost_amount' => 'Coût (informatif)',
        'cost_currency' => 'Devise',
        'lead_days' => 'Préavis (jours)',
        'notes' => 'Remarques',
        'is_active' => 'Actif',
        'course' => 'Formation',
        'version' => 'Version de la formation',
        'versions' => 'Versions de la formation',
        'version_label' => 'Libellé de version',
        'valid_from' => 'Valable à partir du',
        'content_summary' => 'Aperçu du contenu',
        'subject' => 'Public cible',
        'subject_kind' => 'Type de public cible',
        'subject_role' => 'Rôle',
        'subject_team' => 'Domaine d’activité (équipe)',
        'first_due_days' => 'Première échéance (jours)',
        'user' => 'Personne',
        'due_at' => 'Échéance',
        'fulfilled_at' => 'Attesté le',
        'proof' => 'Preuve',
        'state' => 'État',
        'source' => 'Origine',
        'requirements_count' => 'Affectations',
        'assignments_count' => 'Entrées de plan',
    ],

    'action' => [
        'create_course' => 'Créer une formation',
        'create_requirement' => 'Créer une affectation',
        'create_assignment' => 'Créer une entrée de plan',
        'create_version' => 'Créer une version',
        'sync_assignments' => 'Actualiser le plan',
        'edit' => 'Modifier',
        'save' => 'Enregistrer',
        'delete' => 'Supprimer',
        'show' => 'Consulter',
        'back' => 'Retour',
    ],

    'filter' => [
        'all' => 'Toutes',
        'mandatory_only' => 'Obligatoires seulement',
        'state' => 'État',
        'subject_kind' => 'Public cible',
    ],

    'kpi' => [
        'mandatory' => 'Formations obligatoires',
        'active_requirements' => 'Affectations actives',
        'overdue' => 'En retard',
    ],

    'empty' => [
        'courses' => 'Aucune formation au catalogue.',
        'versions' => 'Aucune version de formation créée.',
        'requirements' => 'Aucune obligation affectée.',
        'assignments' => 'Aucune entrée de plan de formation.',
    ],

    'hint' => [
        'cost_informational' => 'Les coûts sont purement informatifs — aucune écriture ni pièce comptable n’est créée.',
        'instruction_course' => 'Avec un lien vers la formation, cette participation vaut preuve pour le plan de formation.',
        'no_second_guard' => 'Le plan de formation alerte et analyse ; le blocage reste géré par le statut de qualification.',
        'proof_in_register' => 'Les preuves sont saisies exclusivement comme instructions dans le registre de sécurité.',
        'sync' => 'L’actualisation crée les entrées manquantes et supprime celles devenues sans objet et sans preuve.',
    ],

    'confirm' => [
        'delete_course' => 'Supprimer la formation ?',
        'delete_version' => 'Supprimer la version ?',
        'delete_requirement' => 'Supprimer l’affectation ?',
        'delete_assignment' => 'Supprimer l’entrée de plan ?',
    ],

    'flash' => [
        'course_created' => 'La formation a été créée.',
        'course_updated' => 'La formation a été mise à jour.',
        'course_deleted' => 'La formation a été supprimée.',
        'version_created' => 'La version a été créée.',
        'version_deleted' => 'La version a été supprimée.',
        'requirement_created' => 'L’affectation a été créée.',
        'requirement_updated' => 'L’affectation a été mise à jour.',
        'requirement_deleted' => 'L’affectation a été supprimée.',
        'assignment_created' => 'L’entrée de plan a été créée.',
        'assignment_deleted' => 'L’entrée de plan a été supprimée.',
        'assignments_synced' => 'Plan actualisé : :created ajoutées, :removed supprimées.',
    ],

    'error' => [
        'delete_with_proof' => 'Cette formation possède des preuves — elle ne peut qu’être désactivée.',
        'delete_last_version' => 'La dernière version de la formation ne peut pas être supprimée.',
        'delete_version_in_use' => 'Cette version est attestée dans une instruction et reste en place.',
    ],

    'report' => [
        'title' => 'Analyse des formations',
        'nav' => 'Formations',
        'subtitle' => 'Taux de conformité par équipe, rôle et formation à la date de référence — base de la preuve de compétence.',
        'total' => 'Total',
        'team' => 'Équipe',
        'role' => 'Rôle',
        'course' => 'Formation',
        'no_team' => 'Sans équipe',
        'no_role' => 'Sans rôle',
        'rate' => 'Taux de conformité',
        'rate_by_team' => 'Taux de conformité par équipe',
        'rate_by_course' => 'Taux de conformité par formation',
        'by_team' => 'Par équipe',
        'by_role' => 'Par rôle',
        'by_course' => 'Par formation',
        'kpi' => [
            'assignments' => 'Entrées de plan',
            'fulfilled' => 'Conformes',
            'due' => 'À échéance',
            'overdue' => 'En retard',
            'rate' => 'Taux de conformité',
        ],
        'empty' => 'Aucune entrée de plan pour le filtre sélectionné.',
    ],
];
