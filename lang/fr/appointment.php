<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : appointment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Eigene Gruppe: die losen Schlüssel „bestätigt“/„storniert“ gehören der Buchhaltung (acknowledged/reversed).
return [
    'status' => [
        'requested' => 'demandé',
        'confirmed' => 'confirmé',
        'declined' => 'refusé',
        'canceled' => 'annulé',
        'proposed' => 'autre créneau proposé',
        'superseded' => 'remplacé',
    ],
    // Kundenportal (Feature 087).
    'portal' => [
        'proposal' => 'Proposition : :date',
        'proposal_accept' => 'Accepter',
        'proposal_decline' => 'Refuser',
        'cancel_expired' => 'Délai d’annulation expiré',
        'cancel_policy' => 'L’annulation est possible jusqu’à :hours heures avant le début du rendez-vous.',
        'cancel_until' => 'Annulable jusqu’au :date',
        'order_cancel_reason' => 'Rendez-vous annulé par le client dans le portail client.',
        'order_in_progress' => 'Ce rendez-vous est déjà en cours de traitement — veuillez nous appeler.',
    ],
    'notification' => [
        'canceled_title' => 'Rendez-vous annulé par :customer',
        'message' => ':service le :date',
        'requested_title' => 'Demande de rendez-vous de :customer',
    ],
    'proposal' => [
        'action' => 'Autre créneau',
        'title' => 'Proposer un autre créneau',
        'start' => 'Créneau proposé',
        'note' => 'Remarque pour le client (facultatif)',
        'submit' => 'Envoyer la proposition',
        'sent' => 'Autre créneau proposé — le client décide dans le portail.',
        'badge' => 'Proposition : :date',
        'in_past' => 'Le rendez-vous se situe dans le passé.',
        'unavailable' => 'Aucune proposition en attente pour cette demande.',
        'accepted' => 'Rendez-vous accepté — vous recevrez une confirmation.',
        'declined' => 'Proposition refusée — la demande est annulée.',
    ],
    'reschedule' => [
        'action' => 'Déplacer',
        'title' => 'Déplacer le rendez-vous',
        'start' => 'Nouveau rendez-vous',
        'submit' => 'Déplacer',
        'done' => 'Rendez-vous déplacé — le client reçoit le nouvel horaire.',
        'not_confirmed' => 'Seuls les rendez-vous confirmés peuvent être déplacés.',
    ],
    'mail' => [
        'proposed_subject' => 'Proposition de rendez-vous : :service',
        'proposed_title' => 'Nous vous proposons un autre rendez-vous',
        'proposed_body' => 'Le créneau souhaité pour :service le :wish n’est malheureusement pas disponible. Nous vous proposons le :date.',
        'proposed_action' => 'Accepter ou refuser dans le portail client',
        'rescheduled_title' => 'Votre rendez-vous a été déplacé',
    ],
];
