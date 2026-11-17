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
    // Übersetzungen von Stammdatentexten (MVP-912).
    'translations' => [
        'title' => 'Traductions',
        'hint' => 'Laissez vide si le texte allemand doit s\'appliquer. La traduction affichée est celle de la langue choisie par la personne.',
    ],
    'collapsible' => [
        'filled' => '{1} :count indication|[2,*] :count indications',
        'has_errors' => 'À vérifier',
    ],
    // Lange Formulare (Feature 203): Abschnitte, Fortschritt, Speichern je Feld, Papierlayout.
    'form' => [
        'progress' => ':answered sur :total répondues',
        'general' => 'Informations générales',
        'contents' => 'Contenu du formulaire',
        'section_state' => 'répondues :',
        'errors_title' => 'Veuillez vérifier ces indications :',
        'errors_count' => 'Indications à vérifier : :count',
        'errors_jump' => 'Aller à la première indication',
        'talk_mode' => 'Mode entretien',
    ],
    'autosave' => [
        'hint' => 'Chaque réponse est enregistrée dès que vous quittez le champ.',
        'saving' => 'Enregistrement …',
        'saved' => 'Toutes les réponses sont enregistrées.',
        'retrying' => 'Pas de connexion – nouvel essai d’enregistrement …',
        'stalled' => 'Non enregistré : pas de connexion. Modifiez à nouveau un champ ou envoyez le formulaire.',
        'conflict' => 'Ce formulaire a été modifié entre-temps ailleurs. Votre dernière saisie n’est pas encore enregistrée.',
        'closed' => 'Ce formulaire a déjà été envoyé et ne peut plus être modifié.',
        'invalid' => 'Cette indication n’a pas été enregistrée – veuillez la vérifier.',
        'keep_mine' => 'Enregistrer ma saisie',
        'reload' => 'Charger la version actuelle',
        'attachment_on_submit' => 'Les fichiers et signatures sont transmis à l’envoi.',
        'unknown_field' => 'Cette question n’existe pas dans ce formulaire ou n’est pas enregistrée séparément.',
        'note_not_allowed' => 'Aucun complément n’est prévu pour cette question.',
    ],
    'note' => [
        'label' => 'Complément',
        'allow' => 'Autoriser un complément',
    ],
    'paper' => [
        'topic' => 'Thème',
        'details' => 'Indications',
        'as_of' => 'État au',
        'reference' => 'Référence',
        'page' => 'Page',
        'of_pages' => 'sur :pages',
    ],
];
