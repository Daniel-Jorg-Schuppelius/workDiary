<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : communication.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Communication',
        'followups' => 'Actions de suivi ouvertes',
    ],

    'field' => [
        'type' => 'Type',
        'direction' => 'Direction',
        'occurred_at' => 'Date et heure',
        'subject' => 'Objet',
        'body' => 'Contenu / déroulement',
        'result' => 'Résultat / accord',
        'next_action' => 'Action de suivi',
        'next_action_due_at' => 'Échéance',
        'next_action_user' => 'Responsable',
        'visibility' => 'Visibilité',
        'confidential' => 'Confidentiel',
        'customer_visible' => 'Visible pour le client',
        'participants' => 'Participants',
        'participant_name' => 'Nom',
        'participant_role' => 'Rôle',
        'participant_party' => 'Partie',
        'creator' => 'Saisi par',
    ],

    'action' => [
        'create' => 'Saisir une note',
        'edit' => 'Modifier',
        'save' => 'Enregistrer',
        'delete' => 'Supprimer',
        'publish' => 'Publier pour le client',
        'mark_confidential' => 'Marquer confidentiel',
        'unmark_confidential' => 'Lever la confidentialité',
        'complete_followup' => 'Suivi terminé',
        'add_participant' => 'Ajouter un participant',
        'remove_participant' => 'Retirer le participant',
    ],

    'flash' => [
        'created' => 'La note de communication a été enregistrée.',
        'updated' => 'La note de communication a été mise à jour.',
        'deleted' => 'La note de communication a été supprimée.',
        'published' => 'La note a été publiée pour le client.',
        'confidential_set' => 'La note a été marquée comme confidentielle.',
        'confidential_unset' => 'La confidentialité a été levée.',
        'followup_completed' => 'L\'action de suivi a été marquée comme terminée.',
    ],

    'error' => [
        'internal_type_requires_internal_direction' => 'Les concertations internes doivent utiliser la direction « Interne ».',
        'internal_direction_requires_internal_visibility' => 'La communication interne ne peut pas être visible pour les clients.',
        'confidential_requires_internal_visibility' => 'Les notes confidentielles doivent rester internes.',
        'occurred_at_in_future' => 'La date ne doit pas être dans le futur.',
        'due_before_occurrence' => 'L\'échéance du suivi doit être postérieure à la date de communication.',
        'unknown_type' => 'Type de communication inconnu.',
        'unknown_direction' => 'Direction inconnue.',
        'confidential_not_publishable' => 'Les notes confidentielles ne peuvent pas être publiées pour les clients.',
        'internal_not_publishable' => 'La communication interne ne peut pas être publiée pour les clients.',
        'no_followup' => 'Cette note n\'a pas d\'action de suivi.',
    ],

    'badge' => [
        'confidential' => 'Confidentiel',
        'followup_done' => 'Terminé',
    ],

    'empty' => 'Aucune note de communication pour le moment.',
    'confirm_delete' => 'Supprimer vraiment cette note de communication ?',
    'confirm_publish' => 'Rendre vraiment cette note visible pour le client ?',
];
