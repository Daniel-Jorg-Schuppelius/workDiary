<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : diary.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'priority' => [
        'low' => 'Basse',
        'normal' => 'Normale',
        'high' => 'Haute',
        'urgent' => 'Urgente',
    ],
    'location_mode' => [
        'onsite' => 'Sur site',
        'remote' => 'À distance',
        'hybrid' => 'Hybride',
    ],
    'mode' => [
        'fixed' => 'Planifié',
        'deadline' => 'Échéance',
        'window' => 'Fenêtre',
        'recurring' => 'Récurrent',
        'backlog' => 'Backlog',
    ],
    'status' => [
        'Planned' => 'Planifié',
        'Accepted' => 'Acceptée',
        'InProgress' => 'En cours',
        'WaitingCustomer' => 'En attente de réponse',
        'WaitingMaterial' => 'En attente de matériel',
        'Completed' => 'Terminé',
        'AcceptedFinal' => 'Réceptionné',
        'Invoiced' => 'Facturée',
        'Cancelled' => 'Annulée',
        'InReview' => 'En vérification',
        'ReviewQueried' => 'Question',
        'ReviewApproved' => 'Validée',
    ],
    'planned_duration' => [
        'label' => 'Durée prévue (HH:MM)',
        'hint' => 'Vide : durée du créneau ou du rendez-vous. Sert de plan dans Plan/réel, Analyse des types de commande et Capacité du personnel.',
        'format' => 'Veuillez saisir la durée prévue en heures:minutes, par ex. 1:30.',
        'range' => 'La durée prévue doit être comprise entre 0:01 et 168:00.',
    ],
    // Kennzahl Plan/Ist am Auftrag (MVP-1552).
    'plan_actual' => [
        'label' => 'Prévu/réel',
        'no_plan' => 'sans prévision',
        'own_only' => 'vos temps uniquement',
    ],
    // Auftrags-Lebenszyklus (MVP-1596).
    'lifecycle' => [
        'action' => [
            'reject' => 'Rendre la prise en charge',
            'revert_invoiced' => 'Annuler la facturation',
            'submit_review' => 'Soumettre à vérification',
            'withdraw_review' => 'Retirer la soumission',
            'approve_review' => 'Valider',
            'query_review' => 'Poser une question',
            'resubmit_review' => 'Soumettre à nouveau',
            'approve_and_complete' => 'Valider et clôturer',
        ],
        'reject' => [
            'title' => 'Rendre la prise en charge',
            'legend' => 'Restitution',
            'hint' => 'L\'ordre repasse à « Planifié » et peut être pris en charge par une autre personne. Le motif figure dans l\'historique.',
        ],
        'handover' => [
            'legend' => 'Réception',
            'accepted_by' => 'Réceptionné par (nom chez le client)',
            'hint' => 'Le procès-verbal ne porte pas de signature du client. Veuillez indiquer la personne qui a réceptionné les travaux chez le client.',
        ],
        'accepted_final_by' => 'Réceptionné par :name le :date',
        'invoiced_with' => 'Facturé avec :reference',
        'invoiced' => [
            'transfer_reference' => 'Transfert n° :id',
        ],
        'cancel' => [
            'run_abort_reason' => 'Ordre annulé : :reason',
        ],
        'error' => [
            'stopwatch_running' => 'L\'ordre ne peut être clôturé que lorsqu\'aucun chronomètre ne tourne. Encore actif : :names.',
        ],
    ],
    // Periodenaufträge mit interner Prüfung (Feature 198), Folgeperiode und Demo.
    'period' => [
        'label' => 'Période',
        'badge' => 'Période : :period',
        'placeholder' => '2026, 2026-H1, 2026-Q1, 2026-03',
        'hint' => 'Année (2026), semestre (2026-H1), trimestre (2026-Q1) ou mois (2026-03).',
        'invalid' => 'Veuillez indiquer la période sous forme d’année (2026), de semestre (2026-H1), de trimestre (2026-Q1) ou de mois (2026-03).',
        'kind_mismatch' => 'Ce type d’entrée exige une période du type : :kinds.',
    ],
    'product_group' => [
        'label' => 'Groupe de produits',
        'invalid' => 'Ce groupe de produits n’existe pas dans votre organisation.',
    ],
    'review' => [
        'required_badge' => 'Vérification obligatoire',
        'reviewer_named' => 'Vérification par :name',
        'submitted_by' => 'Soumise par :name le :date',
        'reviewer_label' => 'Personne vérificatrice',
        'reviewer_any' => 'Toute personne ayant le droit de vérifier',
        'reviewer_hint' => 'Sans choix, toute personne ayant le droit « Vérifier des commandes » vérifie – jamais celle qui soumet.',
        'waive_action' => 'Lever la vérification',
        'require_action' => 'Exiger la vérification',
        'waive_hint' => 'Sans vérification obligatoire, la commande est clôturée directement. Le changement figure avec votre motif dans l’historique et le journal d’audit.',
        'require_hint' => 'Avec vérification obligatoire, la commande est soumise à vérification avant la clôture et validée par une seconde personne.',
        'approve_complete_hint' => 'Ici, la validation est aussi la clôture : vous validez et clôturez la commande en une seule étape.',
        'query' => [
            'title' => 'Question sur la vérification',
            'hint' => 'La commande retourne avec votre question à la personne qui la traite, puis elle est soumise à nouveau. La question figure dans l’historique.',
            'legend' => 'Question',
            'question' => 'Votre question',
        ],
        'error' => [
            'own_submission' => 'La personne qui a soumis une commande à vérification ne peut ni la valider ni poser de question elle-même.',
            'requirement_locked' => 'L’obligation de vérification ne peut plus être modifiée au statut « :status ».',
            'question_required' => 'Une question nécessite un texte.',
            'not_in_review' => 'La commande n’est plus en attente de vérification.',
        ],
        'flash' => [
            'waived' => 'Vérification obligatoire levée pour cette commande.',
            'required' => 'Vérification obligatoire activée pour cette commande.',
        ],
        'inbox_reference' => 'Soumise par :name le :date',
        'inbox_type' => 'Vérification de commande',
        'queue' => [
            'title' => 'À vérifier',
            'subtitle' => 'Commandes que vous pouvez vérifier – par échéance, puis par date de soumission. Vos propres soumissions n’apparaissent pas ici.',
            'empty_title' => 'Rien à vérifier',
            'empty_message' => 'Aucune commande n’attend actuellement votre vérification.',
            'column' => [
                'order' => 'Commande',
                'submitted' => 'Soumise',
                'actions' => 'Actions',
            ],
        ],
        'widget' => [
            'title' => 'À vérifier',
            'description' => 'Commandes en attente de votre vérification interne – par échéance, puis par date de soumission.',
            'open_queue' => 'Toutes →',
        ],
    ],
    'follow_up' => [
        'action' => 'Créer la période suivante',
        'title' => 'Créer la période suivante :period',
        'submit' => 'Créer',
        'copied' => 'Sont reprises : client, type d’entrée, groupe de produits, responsable, vérification obligatoire et personne vérificatrice ; les procédures démarrent en nouvelles exécutions. Les mémos ouverts apparaissent dans la nouvelle commande.',
        'not_copied' => 'Ne sont pas repris : contenu et notes, temps et résultat.',
        'exists_hint' => 'Pour :period, cette commande existe déjà :',
        'content' => 'Période suivante :period de la commande n° :id.',
        'error' => [
            'no_series' => 'La période suivante n’existe que pour les commandes avec période, client et type d’entrée.',
            'exists' => 'La commande existe déjà pour cette période (n° :id).',
            'selection' => 'Veuillez choisir un type d’entrée et une période.',
        ],
        'flash' => [
            'created' => 'Commande pour :period créée.',
            'bulk' => ':created commandes pour :period créées, :skipped ignorées.',
        ],
        'bulk' => [
            'title' => 'Période suivante en lot',
            'subtitle' => 'Créer la commande de la période suivante pour toutes les commandes d’un type d’entrée et d’une période – d’abord l’aperçu, puis la création.',
            'choose_title' => 'Choisir le type d’entrée et la période',
            'choose_message' => 'L’aperçu montre quelles commandes seront créées et lesquelles existent déjà.',
            'none_title' => 'Aucune commande',
            'none_message' => 'Il n’y a aucune commande de ce type d’entrée en :period.',
            'summary' => ':count commandes en :from, dont :creatable nouvelles pour :to.',
            'limit' => 'Au plus :limit commandes sont traitées à la fois.',
            'submit' => 'Créer :count commandes',
            'column' => [
                'source' => 'Commande',
                'target' => 'Nouvelle période',
                'result' => 'Résultat',
            ],
            'result' => [
                'create' => 'sera créée',
                'exists' => 'existe déjà',
                'no_customer' => 'sans client',
                'duplicate_source' => 'en double dans l’aperçu',
            ],
        ],
    ],
    'demo' => [
        'entry_type' => 'Commande annuelle (démo)',
        'title' => 'Commande annuelle :year',
        'content' => 'Commande annuelle de démonstration : réunir les documents, les traiter, les soumettre à vérification.',
        'approval_note' => 'Vérifié, aucune remarque.',
        'summary' => 'Documents complets, résultat transmis.',
        'carry_forward' => 'L’an prochain, demander les justificatifs avant fin février.',
    ],
    'entry_type' => [
        'legend' => 'Période et vérification',
        'description' => 'Commandes périodiques (année, semestre, trimestre, mois) et vérification interne avant la clôture.',
        'requires_period' => 'Période obligatoire à la création',
        'requires_period_hint' => 'Sans choix, la période est facultative. Avec plusieurs types, chacun convient.',
        'requires_review' => 'Vérification obligatoire avant la clôture (principe des quatre yeux)',
    ],
];
