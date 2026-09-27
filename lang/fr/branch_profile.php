<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : branch_profile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Branchenprofile: kundenspezifische Varianten (MVP-933).
return [
    'variant' => [
        'title' => 'Variante de profil',
        'list' => 'Variantes spécifiques au client',
        'create' => 'Créer une variante',
        'subtitle' => 'Surcouche de « :base » · version :version',
        'empty' => 'Aucune variante. Une variante reprend un profil sectoriel, retire des éléments et en ajoute.',
        'edit' => 'Modifier',
        'save' => 'Enregistrer la variante',
        'install' => 'Installer',
        'force' => 'Mettre à jour les entrées existantes',
        'export' => 'Exporter en JSON',
        'delete' => 'Supprimer',
        'confirm_delete' => 'Supprimer la variante ? Les entrées déjà installées sont conservées.',
        'installed' => 'Installée : variante :code, version :version.',
        'not_installed' => 'Aucune variante de ce profil de base n\'est installée.',
        'removals' => 'Retirer des éléments',
        'additions' => 'Ajouts',
        'field' => [
            'base_code' => 'Profil de base',
            'code' => 'Code',
            'label' => 'Intitulé',
            'description' => 'Description',
            'additions' => 'Extrait de profil (JSON)',
        ],
        'hint' => [
            'code' => 'Minuscules, chiffres et tirets.',
            'removals' => 'Les éléments cochés du profil de base ne sont pas créés à l\'installation. Les entrées existantes restent inchangées.',
            'additions' => 'Même structure qu\'un profil sectoriel, p. ex. {"tags_seed": ["#propre"]}. Les éléments de même code remplacent ceux du profil de base.',
        ],
        'section' => [
            'entry_type_defaults' => 'Types d\'entrée par défaut',
            'modules_recommended' => 'Modules recommandés',
            'classifications' => 'Classifications',
            'classification_requirements' => 'Règles obligatoires',
            'procedure_templates' => 'Modèles de procédure',
            'protocol_templates' => 'Modèles de procès-verbal',
            'asset_categories' => 'Catégories d\'objets',
            'tags_seed' => 'Tags',
            'maintenance_plans_seed' => 'Plans de maintenance',
            'sla_contracts_seed' => 'Contrats SLA',
            'dataprotection_requirements_seed' => 'Exigences de protection des données',
            'contract_templates' => 'Modèles de contrat',
            'training_suggestions' => 'Suggestions de formation',
            'room_requirement_templates_seed' => 'Exigences de salle',
            'qualifications_seed' => 'Qualifications',
            'custom_fields' => 'Champs personnalisés',
            'cleaning_profiles_seed' => 'Profils de nettoyage',
            'software_seed' => 'Logiciels',
        ],
        'flash' => [
            'created' => 'Variante créée.',
            'saved' => 'Variante enregistrée.',
            'installed' => 'Variante « :label » installée.',
            'deleted' => 'Variante supprimée.',
        ],
        'error' => [
            'json' => 'Les ajouts doivent être un objet JSON.',
        ],
    ],
];
