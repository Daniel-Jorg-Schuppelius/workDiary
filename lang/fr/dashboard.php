<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : dashboard.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'width' => [
        'half' => 'Demi-largeur',
        'full' => 'Pleine largeur',
    ],

    'group' => [
        'overview' => 'Vue d\'ensemble',
        'time' => 'Temps',
        'tasks' => 'Tâches',
        'activity' => 'Activité',
        'deadlines' => 'Échéances',
        'finance' => 'Finances',
        'operations' => 'Exploitation',
    ],

    'widget' => [
        'personal_kpis' => [
            'description' => 'Entrées ouvertes, travaux en cours, gardes et astreintes à venir.',
        ],
        'team_kpis' => [
            'description' => 'Entrées ouvertes et en cours de l\'équipe, archivées aujourd\'hui, effectif.',
        ],
        'today_shifts' => [
            'description' => 'Vos gardes du jour.',
        ],
        'upcoming_shifts' => [
            'description' => 'Vos prochaines astreintes et gardes.',
        ],
        'emergencies' => [
            'description' => 'Interventions d\'astreinte à venir.',
        ],
        'scheduled_shifts' => [
            'description' => 'Planning des sept prochains jours.',
        ],
        'open_issues' => [
            'description' => 'Points ouverts qui vous sont attribués — par échéance.',
        ],
        'recent_entries' => [
            'description' => 'Vos entrées modifiées récemment.',
        ],
        'recent_comments' => [
            'description' => 'Nouveaux commentaires sur vos entrées.',
        ],
        'recent_attachments' => [
            'description' => 'Nouvelles pièces jointes sur vos entrées.',
        ],
        'team_activity' => [
            'description' => 'Les derniers commentaires de l\'équipe.',
        ],
        'finance' => [
            'description' => 'Notes de frais et déplacements du mois, plus la pile en attente pour les valideurs.',
        ],
        'vacation' => [
            'description' => 'Demandes de congés en attente et jours approuvés cette année.',
        ],
        'onboarding' => [
            'description' => 'Progression de la liste de mise en route.',
        ],
        'attendance_clock' => [
            'description' => 'Pointage entrée/sortie, pauses et statuts intermédiaires.',
        ],
        'bookmarks' => [
            'description' => 'Vos favoris enregistrés.',
        ],
        'data_protection' => [
            'description' => 'Revues du registre en retard et demandes des personnes concernées ouvertes.',
        ],
        'operations_tasks' => [
            'description' => 'Tâches d\'exploitation ouvertes par urgence.',
        ],
        'stopwatch' => [
            'description' => 'Le chronomètre en cours avec projet et description.',
        ],
        'flex_balance' => [
            'description' => 'Solde horaire du dernier mois arrêté, avec feu tricolore.',
        ],
        'time_accounts' => [
            'description' => 'Soldes de vos comptes de temps (heures supplémentaires, comptes spéciaux).',
        ],
        'time_corrections' => [
            'description' => 'Vos demandes de correction en cours ou soumises.',
        ],
        'reminders' => [
            'description' => 'Éléments à traiter : notes de frais, déplacements et congés — comme sous la cloche.',
        ],
        'kanban_status' => [
            'description' => 'Combien de vos ordres se trouvent dans chaque colonne Kanban.',
        ],
        'service_tickets' => [
            'description' => 'Tickets ouverts qui vous sont attribués.',
        ],
        'chat_unread' => [
            'description' => 'Messages non lus par canal.',
        ],
        'approvals' => [
            'description' => 'Notes de frais et demandes de congés en attente de votre décision.',
        ],
        'asset_compliance' => [
            'description' => 'Contrôles en retard et à venir du calendrier de contrôle.',
        ],
        'asset_blocks' => [
            'description' => 'Objets actuellement bloqués, avec le motif.',
        ],
        'contract_deadlines' => [
            'description' => 'Obligations et échéances contractuelles des prochaines semaines.',
        ],
        'leasing_deadlines' => [
            'description' => 'Échéances de résiliation, de restitution et de reconduction des dossiers de leasing.',
        ],
        'safety_due' => [
            'description' => 'Revues à venir des évaluations des risques et des visites médicales.',
        ],
        'training_due' => [
            'description' => 'Vos obligations de formation et d’instruction ouvertes.',
        ],
        'open_times' => [
            'description' => 'Temps facturables qui ne figurent encore sur aucune facture.',
        ],
        'open_items' => [
            'description' => 'Créances et dettes ouvertes, part échue comprise.',
        ],
        'tax_filings' => [
            'description' => 'Prochaines échéances de déclaration en comptabilité.',
        ],
        'integration_inbox' => [
            'description' => 'Postes importés encore sans affectation.',
        ],
        'backup_status' => [
            'description' => 'Fraîcheur des sauvegardes, par source.',
        ],
        'plugin_health' => [
            'description' => 'Plugins dont le dernier contrôle de santé a échoué.',
        ],
    ],

    'preset' => [
        'classic' => [
            'label' => 'Tableau de bord classique',
            'description' => 'Indicateurs et favoris en haut, puis les quatre sections Vue d’ensemble, Tâches, Activité et Finances — le tableau de bord d’avant la refonte en tuiles, plus la pointeuse.',
        ],
    ],
];
