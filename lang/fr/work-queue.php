<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : work-queue.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'File de travail',
    'subtitle' => 'Tout ce que vous devriez traiter maintenant — validations, boîtes de réception, tâches et rappels. Chaque élément se traite sur sa propre page.',
    'filter' => [
        'category' => 'Domaine',
        'all_categories' => 'Tous les domaines',
        'source' => 'Source',
        'all_sources' => 'Toutes les sources',
        'mine' => 'Uniquement ceux qui me sont attribués',
    ],
    'column' => [
        'urgency' => 'Urgence',
        'item' => 'Élément',
        'source' => 'Source',
        'due' => 'Échéance',
        'count' => 'Nombre',
        'assignment' => 'Responsabilité',
    ],
    'assignment' => [
        'personal' => 'Attribué à vous',
        'shared' => 'Par votre rôle',
    ],
    'empty' => [
        'title' => 'Rien à faire',
        'message' => 'Aucun élément ne vous attend pour le moment.',
    ],
    'source' => [
        'approval' => 'Circuits de validation',
        'vacation_approval' => 'Demandes de congé',
        'expense_approval' => 'Notes de frais',
        'month_closure' => 'Clôtures mensuelles',
        'price_change' => 'Modifications de prix',
        'learning_time' => 'Temps de formation',
        'integration_inbox' => 'Boîte d\'affectation',
        'invoice_inquiry' => 'Demandes sur factures',
        'incoming_invoice' => 'Factures entrantes',
        'open_issue' => 'Points ouverts',
        'assigned_entry' => 'Commandes',
        'task' => 'Tâches de projet',
        'service_ticket' => 'Tickets de service',
        'draft_reminder' => 'Vos brouillons',
        'chat_reminder' => 'Rappels de chat',
    ],
    'item' => [
        'vacation' => 'Demande de congé de :name',
        'expense' => 'Note de frais de :name',
        'month_closure' => 'Mois :period de :name',
        'price_change' => 'Modification de prix : :article',
        'learning_time' => 'Temps de formation de :name',
        'integration_inbox' => 'Affectations ouvertes : :source',
        'invoice_inquiry' => 'Demande sur la facture :number',
        'incoming_invoice' => [
            'assign' => 'Affecter les factures entrantes',
            'review' => 'Vérifier les factures entrantes',
            'failed' => 'Transmissions échouées',
            'duplicates' => 'Doublons possibles',
        ],
        'entry_untitled' => 'Commande sans titre',
        'chat_reminder' => 'Rappel de chat',
    ],
    'detail' => [
        'days_open' => ':days jours non clôturés',
    ],
    'widget' => [
        'label' => 'File de travail',
        'description' => 'Les éléments ouverts les plus urgents de tous les domaines.',
        'all' => 'Tout afficher',
        'empty' => 'Aucun élément ne vous attend pour le moment.',
    ],
];
