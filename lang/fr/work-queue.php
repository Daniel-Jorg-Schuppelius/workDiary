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
        'project_target' => 'Temps cibles de projet',
        'resale_plan' => 'Abonnements et licences : modèles récurrents',
        'resale' => 'Abonnements et licences',
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
        'overtime_request' => 'Demandes d\'heures supplémentaires',
        'time_correction' => 'Corrections de temps',
        'day_correction' => 'Corrections de clôture journalière',
        'duty_plan' => 'Plannings de service à approuver',
        'shift_exchange' => 'Échanges de poste',
        'personnel_file_submission' => 'Dossier personnel : dépôts',
        'customer_intake' => 'Demandes clients',
        'rental_request' => 'Demandes de location',
        'learning_booking' => 'Réservations de cours',
        'supplier_questionnaire' => 'Auto-évaluations des fournisseurs',
        'club_membership' => 'Groupes de l\'association : demandes d\'admission',
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
        'overtime_request' => 'Heures supplémentaires de :name',
        'time_correction' => 'Correction de temps de :name',
        'time_correction_apply' => 'Appliquer la correction de temps de :name',
        'day_correction' => 'Corriger la clôture journalière de :name',
        'duty_plan' => 'Planning de service « :title »',
        'shift_exchange' => [
            'accept' => 'Demande d\'échange de :name',
            'approve' => 'Échange de poste de :from à :to',
        ],
        'personnel_file_submission' => 'Dépôt de :name : :title',
        'customer_intake' => 'Demande client :number : :subject',
        'rental_request' => 'Demande de location de :customer',
        'learning_booking' => 'Réservation de cours de :name',
        'supplier_questionnaire' => 'Auto-évaluation de :supplier',
        'club_membership' => 'Admission de :name dans « :group »',
    ],
    'detail' => [
        'days_open' => ':days jours non clôturés',
        'minutes' => ':minutes min',
    ],
    'widget' => [
        'label' => 'File de travail',
        'description' => 'Les éléments ouverts les plus urgents de tous les domaines.',
        'all' => 'Tout afficher',
        'empty' => 'Aucun élément ne vous attend pour le moment.',
    ],
    'overflow' => '{1} Un élément de plus n’est pas listé : la file affiche au plus :limit par source.|[2,*] :count éléments de plus ne sont pas listés : la file affiche au plus :limit par source.',
];
