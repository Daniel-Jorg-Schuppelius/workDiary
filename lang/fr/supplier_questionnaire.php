<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : supplier_questionnaire.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Lieferanten-Selbstauskunft (MVP-937).
return [
    'title' => 'Auto-évaluation des fournisseurs',
    'subtitle' => 'Questionnaires aux fournisseurs (p. ex. durabilité, chaîne d\'approvisionnement, qualité) avec lien unique, contrôle et validité.',
    'card' => 'Auto-évaluation',
    'questionnaires' => 'Questionnaires',
    'recent' => 'Demandes récentes',
    'create' => 'Créer un questionnaire',
    'edit' => 'Modifier le questionnaire',
    'save' => 'Enregistrer',
    'send' => 'Demander une auto-évaluation',
    'send_hint' => 'Le fournisseur reçoit par e-mail un lien valable :days jours. Une demande ouverte du même questionnaire est retirée.',
    'open' => 'Ouvrir',
    'none' => 'Aucune auto-évaluation demandée.',
    'empty' => 'Aucun questionnaire.',
    'no_requests' => 'Aucune demande.',
    'inactive' => 'inactif',
    'valid_until' => 'valable jusqu\'au :date',
    'submitted_at' => 'soumis le :date',
    'waiting' => 'En attente de réponse de :email (lien valable jusqu\'au :date).',
    'accept' => 'Accepter',
    'reject' => 'Renvoyer pour correction',
    'public_title' => 'Auto-évaluation pour :org',
    'public_submit' => 'Envoyer les réponses',
    'public_thanks' => 'Merci, vos réponses ont bien été reçues.',
    'public_rework' => 'Veuillez compléter vos réponses : :note',
    'field' => [
        'name' => 'Questionnaire',
        'description' => 'Note pour le fournisseur',
        'questions' => 'Questions',
        'validity_months' => 'Validité (mois)',
        'is_active' => 'Actif',
        'requests' => 'Demandes',
        'recipient_email' => 'E-mail du fournisseur',
        'sent_at' => 'Demandé',
        'status' => 'Statut',
        'valid_until' => 'Valable jusqu\'au',
        'note' => 'Remarque',
    ],
    'status' => [
        'sent' => 'Demandé',
        'submitted' => 'Soumis',
        'accepted' => 'Accepté',
        'rejected' => 'Renvoyé pour correction',
        'withdrawn' => 'Retiré',
    ],
    'flash' => [
        'saved' => 'Questionnaire enregistré.',
        'sent' => 'Demande envoyée à :email.',
        'reviewed' => 'Contrôle enregistré.',
    ],
    'error' => [
        'transition' => 'L\'auto-évaluation ne peut pas passer de « :from » à « :to ».',
    ],
    'mail' => [
        'subject' => 'Auto-évaluation pour :org',
        'body' => "Bonjour,\n\n:org vous demande de remplir l'auto-évaluation « :name ». Veuillez compléter le questionnaire avant le :until :\n:url\n\nMerci.",
    ],
];
