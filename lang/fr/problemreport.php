<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : problemreport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'create' => 'Signaler un problème',
        'eyebrow' => 'Problème technique',
        'index' => 'Mes signalements',
        'index_subtitle' => 'Vos problèmes techniques signalés avec numéro de référence et statut.',
        'inbox' => 'Signalements de problèmes',
        'inbox_subtitle' => 'Signalements techniques entrants — examiner, répondre, convertir en ticket.',
    ],
    'section' => [
        'what' => 'Que s\'est-il passé ?',
        'context' => 'Informations transmises',
    ],
    'field' => [
        'summary' => 'Résumé',
        'description' => 'Description',
        'expected' => 'Comportement attendu',
        'actual' => 'Comportement observé',
        'severity' => 'Gravité',
        'screenshots' => 'Captures d\'écran/pièces jointes (max. 3)',
        'contact_ok' => 'Le support peut me contacter au sujet de ce signalement.',
        'contact_ok_short' => 'Contact ok',
        'include_diagnostics' => 'Joindre un extrait de diagnostic anonymisé (recommandé)',
        'reference' => 'Référence',
        'status' => 'Statut',
        'created_at' => 'Signalé le',
        'reporter' => 'Auteur',
        'diagnostics' => 'Extrait de diagnostic (anonymisé)',
        'delivery_error' => 'Erreur d\'envoi',
        'ticket' => 'Ticket',
    ],
    'severity' => [
        'low' => 'Faible',
        'normal' => 'Normal',
        'high' => 'Élevé',
        'blocking' => 'Bloquant',
    ],
    'status' => [
        'new' => 'Nouveau',
        'in_review' => 'En cours d\'examen',
        'answered' => 'Répondu',
        'closed' => 'Fermé',
    ],
    'delivery' => [
        'saas_inbox' => 'Boîte de support (ce système)',
        'mail' => 'E-mail de support',
        'webhook' => 'Webhook',
        'local_export' => 'Export local',
    ],
    'action' => [
        'submit' => 'Envoyer le signalement',
        'open' => 'Ouvrir',
        'set_status' => 'Définir le statut',
        'download' => 'Télécharger en JSON',
        'convert' => 'Convertir en ticket',
    ],
    'hint' => [
        'context' => 'Ces informations techniques sont transmises avec votre signalement — aucune donnée client ou de commande.',
        'diagnostics_always' => 'Selon la règle de l\'organisation, un extrait de diagnostic anonymisé est joint.',
        'diagnostics_preview' => 'Voir l\'extrait de diagnostic (transmis exactement ainsi)',
        'no_diagnostics' => 'Aucun extrait de diagnostic joint (décision de l\'auteur ou règle de l\'organisation).',
    ],
    'context' => [
        'route' => 'Page',
        'topic' => 'Rubrique d\'aide',
        'version' => 'Version de l\'application',
    ],
    'empty' => [
        'title' => 'Aucun signalement',
        'message' => 'Vous n\'avez encore signalé aucun problème technique.',
        'inbox_title' => 'Aucun signalement',
        'inbox_message' => 'Il n\'y a actuellement aucun signalement technique.',
    ],
    'filter' => [
        'all_statuses' => 'Tous les statuts',
    ],
    'flash' => [
        'created' => 'Merci ! Votre signalement a été enregistré sous :reference.',
        'status_updated' => 'Statut mis à jour.',
        'converted' => 'Converti en ticket :reference.',
        'already_converted' => 'Déjà converti en ticket :reference.',
    ],
    'mail' => [
        'heading' => 'Signalement :reference',
        'contact_ok' => ':name accepte les questions complémentaires.',
        'attachment_hint' => 'L\'enregistrement anonymisé complet est joint au format JSON.',
    ],
];
