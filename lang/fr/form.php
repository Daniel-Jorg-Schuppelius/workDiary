<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : form.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'templates' => 'Modèles de formulaire',
        'template' => 'Modèle',
        'submissions' => 'Formulaires',
        'submission' => 'Formulaire rempli',
        'values' => 'Saisies',
        'panel' => 'Formulaires',
    ],

    'subtitle' => [
        'templates' => 'Gérer des formulaires configurables (rapports, check-lists) sans code.',
        'submissions' => 'Formulaires remplis — sécurisés par l’instantané de la définition des champs.',
    ],

    'field' => [
        'name' => 'Nom',
        'valid_from' => 'Valable à partir du',
        'valid_until' => "Valable jusqu'au",
        'target_entry_type' => 'Affectation : type de mission',
        'target_customer' => 'Affectation : client',
        'description' => 'Description',
        'status' => 'Statut',
        'fields' => 'Champs',
        'submissions' => 'Remplis',
        'creator' => 'Créé par',
        'template' => 'Modèle',
        'subject' => 'Référence',
        'submitted_by' => 'Rempli par',
        'submitted_at' => 'Rempli le',
        'field_label' => 'Libellé du champ',
        'field_type' => 'Type de champ',
        'field_required' => 'Obligatoire',
        'field_options' => 'Options',
        'field_help' => 'Texte d’aide',
        'field_unit' => 'Unité',
    ],

    'action' => [
        'create_template' => 'Créer un modèle',
        'edit' => 'Modifier',
        'save' => 'Enregistrer',
        'activate' => 'Activer',
        'archive' => 'Archiver',
        'delete' => 'Supprimer',
        'add_field' => 'Ajouter un champ',
        'remove_field' => 'Retirer le champ',
        'fill' => 'Remplir le formulaire',
        'submit' => 'Envoyer',
        'show' => 'Consulter',
        'print' => 'Imprimer',
        'download_pdf' => 'Télécharger le PDF',
        'clear_signature' => 'Effacer la signature',
        'back' => 'Retour',
    ],

    'filter' => [
        'all' => 'Tous',
        'search' => 'Recherche',
        'search_placeholder' => 'Rechercher un nom de modèle',
        'period' => 'Période',
    ],

    'hint' => [
        'options' => 'Séparées par des virgules, p. ex. bon, moyen, mauvais',
        'unit' => 'p. ex. kWh, °C, pièces',
    ],

    'subject_kind' => [
        'diary' => 'Mission',
        'customer' => 'Client',
        'asset' => 'Équipement',
        'project' => 'Projet',
    ],

    'value' => [
        'yes' => 'Oui',
        'no' => 'Non',
        'signed' => 'Signé',
    ],

    'condition' => [
        'legend' => 'Visible si',
        'always' => '— toujours visible —',
        'value_placeholder' => 'Valeur de comparaison',
        'op' => [
            'eq' => 'égal à',
            'ne' => 'différent de',
            'in' => 'l’un de (virgule)',
            'filled' => 'renseigné',
        ],
    ],

    // Offline erfasste Anhänge (Audit 2026-08, W4.1).

    'attachment' => ['pending' => 'Téléversement en attente'],

    'validation' => [

        'no_upload_field' => 'Aucun champ fichier/photo ne porte cette clé dans le formulaire.',
        'invalid_row' => 'La définition du champ à la ligne :row est invalide.',
        'label_required' => 'Le champ :row nécessite un libellé (max. 160 caractères).',
        'unknown_type' => 'Le champ :row a un type inconnu.',
        'invalid_key' => 'La clé de champ « :key » est invalide (minuscules, chiffres, tirets bas).',
        'duplicate_key' => 'La clé de champ « :key » est utilisée plusieurs fois.',
        'select_needs_options' => 'Le champ de sélection « :label » nécessite au moins une option.',
        'fields_required' => 'Le modèle nécessite au moins un champ.',
        'too_many_fields' => 'Au maximum :max champs par modèle.',
        'template_not_active' => 'Ce modèle n’est pas actif et ne peut pas être rempli.',
        'condition_unknown_field' => 'La condition du champ « :label » référence un champ inconnu « :field ».',
        'condition_cycle' => 'Les conditions forment un cycle (le champ « :field » dépend indirectement de lui-même).',
    ],

    'flash' => [
        'template_created' => 'Le modèle a été créé.',
        'template_updated' => 'Le modèle a été mis à jour.',
        'template_activated' => 'Le modèle a été activé.',
        'template_archived' => 'Le modèle a été archivé.',
        'template_deleted' => 'Le modèle a été supprimé.',
        'submitted' => 'Le formulaire a été enregistré.',
    ],

    'empty_templates_title' => 'Aucun modèle trouvé',
    'empty_templates' => 'Aucun modèle de formulaire pour l’instant.',
    'empty_submissions_title' => 'Aucun formulaire trouvé',
    'empty_submissions' => 'Aucun formulaire rempli pour l’instant.',
    'empty_filtered' => 'Aucune entrée trouvée pour les filtres actuels.',
    'empty_panel' => 'Aucun formulaire rempli pour cet enregistrement.',
    'confirm_archive' => 'Archiver vraiment ce modèle ? Il disparaîtra de la sélection de remplissage.',
    'confirm_delete' => 'Supprimer vraiment ce modèle ? Les formulaires remplis sont conservés.',
];
