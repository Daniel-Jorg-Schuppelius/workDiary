<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : fields.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Feldschema-Baustein (MVP-866): Validierung der Definitionen, Anzeigewerte.
return [
    'validation' => [
        'invalid_row' => 'La définition du champ à la ligne :row est invalide.',
        'label_required' => 'Le champ :row a besoin d’un libellé (160 caractères max.).',
        'unknown_type' => 'Le champ :row a un type inconnu.',
        'invalid_key' => 'La clé de champ « :key » est invalide (minuscules, chiffres, tirets bas).',
        'duplicate_key' => 'La clé de champ « :key » est utilisée deux fois.',
        'select_needs_options' => 'Le champ à choix « :label » a besoin d’au moins une option.',
        'fields_required' => 'Au moins un champ est requis.',
        'too_many_fields' => 'Au plus :max champs.',
        'range_invalid' => 'Champ « :label » : le minimum ne doit pas dépasser le maximum.',
        'condition_unknown_field' => 'La condition du champ « :label » renvoie à un champ inconnu « :field ».',
        'condition_cycle' => 'Les conditions forment un cycle (le champ « :field » dépend indirectement de lui-même).',
    ],
    'value' => [
        'yes' => 'Oui',
        'no' => 'Non',
        'signed' => 'Signé',
        'attachments' => '{1} :count pièce jointe|[2,*] :count pièces jointes',
    ],
    'action' => [
        'clear_signature' => 'Effacer la signature',
    ],
    'custom' => [
        'listed' => 'Afficher dans la liste',
        'title' => 'Champs personnalisés',
        'legend' => 'Champs supplémentaires',
        'subject' => 'Objet',
        'fields' => 'Champs',
        'status' => 'Statut',
        'active' => 'Actif',
        'inactive' => 'Désactivé',
        'none' => 'Aucun champ défini pour l’instant.',
        'edit' => 'Modifier les champs',
        'save' => 'Enregistrer',
        'activate' => 'Activer',
        'deactivate' => 'Désactiver',
        'values_count' => ':count enregistrements avec valeurs',
        'version' => 'Version du schéma :version',
        'saved' => 'Champs personnalisés enregistrés.',
        'toggled' => 'Statut modifié.',
        'intro' => 'Une liste de champs par objet ; les champs apparaissent dans le formulaire, sur la page de détail et dans les exports. Désactivez au lieu de supprimer dès que des valeurs existent.',
        'validation' => [
            'too_many_listed' => 'Au plus :max champs peuvent apparaître dans la liste.',
            'unknown_subject' => 'Cet objet n’a pas de champs personnalisés.',
            'type_not_allowed' => 'Champ « :label » : les champs fichier, photo et signature ne sont pas disponibles ici.',
        ],
    ],
];
