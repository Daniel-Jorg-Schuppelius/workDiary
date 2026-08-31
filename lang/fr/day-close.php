<?php
/*
 * Created on   : Fri Jun 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : day-close.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Clôture journalière (MVP-015, docs/tagesabschluss.md) — textes de page,
 * messages du validateur (§4), messages flash et erreurs. Maintenu en
 * parité de/en/fr/it/es ; les libellés d'enums se trouvent dans enums.php
 * (dayClosure.status / dayCorrection.status).
 */

return [
    'title' => 'Clôture journalière',
    'title_day' => 'Clôture journalière :day',

    'subtitle' => [
        'own' => 'Vérifier la journée, combler les lacunes et la clôturer comme entièrement saisie.',
        'other' => 'Clôture journalière de :name.',
    ],

    'section' => [
        'attendance' => 'Présence',
        'breaks' => 'Pauses',
        'entries' => 'Temps de commande et de projet',
        'issues' => 'Lacunes & avertissements',
        'balance' => 'Bilan',
        'corrections' => 'Demandes de correction',
    ],

    'field' => [
        'date' => 'Date',
        'recorded_break' => 'Pause enregistrée',
        'required_break' => 'Pause obligatoire',
        'target' => 'Heures cibles',
        'gross' => 'Présence (brut)',
        'break' => 'Pause',
        'net' => 'Travail net',
        'booked' => 'Imputé',
        'diff' => 'Différence',
        'day_balance' => 'Solde du jour',
        'month_balance' => 'Solde du mois en cours',
        'duration' => 'Durée',
        'project' => 'Commande / projet',
        'activity' => 'Activité',
        'comment' => 'Commentaire',
        'billable' => 'Facturable',
        'reason' => 'Justification',
        'reason_placeholder' => 'Justification (au moins :min caractères)',
        'decision' => 'Décision',
    ],

    'action' => [
        'prev_day' => 'Jour précédent',
        'next_day' => 'Jour suivant',
        'today' => 'Aujourd\'hui',
        'pick_date' => 'Choisir une date',
        'show_day' => 'Afficher le jour',
        'clock_in' => 'Pointer maintenant',
        'clock_out' => 'Dépointer maintenant',
        'book_time' => 'Imputer du temps',
        'save' => 'Enregistrer',
        'close_day' => 'Clôturer la journée',
        'request_correction' => 'Demander une correction',
        'reopen' => 'Rouvrir la journée',
        'approve' => 'Approuver',
        'reject' => 'Rejeter',
        'cancel' => 'Annuler',
    ],

    'status' => [
        'attendance_open' => 'ouvert',
        'comment_missing' => 'manquant',
        'billable' => 'facturable',
    ],

    'hint' => [
        'no_attendance' => 'Aucun pointage ce jour-là.',
        'attendance_correction_only' => 'Les pointages ne peuvent être modifiés que via une demande de correction.',
        'attendance_locked' => 'Les pointages de présence sont verrouillés après l\'approbation d\'une correction — seules les imputations restent modifiables jusqu\'à la prochaine clôture.',
        'no_entries' => 'Encore aucune imputation ce jour-là.',
        'break_recorded' => 'Pause : :min min',
        'no_issues' => 'Aucune anomalie — la journée est saisie de manière cohérente.',
        'month_locked' => 'Ce jour appartient à un mois approuvé et est verrouillé — la clôture et les demandes de correction passent par l\'approbation mensuelle.',
        'correction_intro' => 'Décrivez ce qui doit être corrigé ce jour-là.',
        'reopen_intro' => 'La journée est rouverte sans demande de correction — la justification est enregistrée dans le journal d\'audit.',
    ],

    // Les 7 contrôles de cohérence du §4 — clé = code du contrôle
    // (DayClosureValidator), les points du code deviennent une imbrication.
    'check' => [
        'attendance' => [
            'missing_close' => 'La pointeuse est encore ouverte — veuillez dépointer.',
        ],
        'time' => [
            'unallocated_minutes' => ':minutes minutes de présence ne sont pas encore affectées à une imputation.',
            'gap_in_attendance' => 'Lacune de présence de :minutes minutes sans marqueur de pause.',
        ],
        'break' => [
            'required' => 'Pause obligatoire non respectée : :taken minutes enregistrées sur :required.',
        ],
        'balance' => [
            'threshold' => 'Le solde du jour de :hours heures dépasse ±2 h.',
        ],
        'entry' => [
            'missing_comment' => ':count imputation(s) facturable(s) sans commentaire.',
        ],
        'worktime' => [
            'overrun' => 'Temps de travail net supérieur à 10 heures (:minutes minutes, ArbZG).',
        ],
        'unknown' => 'Contrôle inconnu : :code',
    ],

    'flash' => [
        'saved' => 'La journée :day a été enregistrée.',
        'closed' => 'La journée :day a été clôturée.',
        'correction_requested' => 'La correction pour :day a été demandée.',
        'correction_approved' => 'La correction pour :day a été approuvée.',
        'correction_rejected' => 'La correction pour :day a été rejetée.',
        'reopened' => 'La journée :day a été rouverte.',
    ],

    'errors' => [
        'month_entry_locked' => 'Le mois est validé — pour des temps ultérieurs, veuillez déposer une demande de correction.',
        'future_day' => 'Un jour futur ne peut pas être clôturé.',
        'blocking_warnings' => 'La journée comporte des avertissements bloquants et ne peut pas être clôturée.',
        'illegal_day_status' => 'Action non autorisée : le statut du jour est :status.',
        'illegal_request_status' => 'Action non autorisée : le statut de la demande est :status.',
        'reason_too_short' => 'Une justification d\'au moins :n caractères est requise.',
        'month_locked' => 'Le mois est déjà approuvé ou verrouillé — veuillez d\'abord rouvrir l\'approbation mensuelle.',
        'owner_missing' => 'Clôture journalière sans propriétaire valide.',
        'closure_missing' => 'Demande de correction sans clôture journalière associée.',
        // Justifications de l'info-bulle du bouton de clôture désactivé (§2.6).
        'close_blocked' => [
            'future' => 'Un jour futur ne peut pas être clôturé.',
            'month_locked' => 'Le mois est déjà approuvé ou verrouillé.',
            'blocking' => 'Les avertissements bloquants (⛔) doivent d\'abord être résolus.',
            'not_open' => 'La journée n\'est pas ouverte.',
        ],
    ],

    'modal' => [
        'correction_title' => 'Demander une correction',
        'reopen_title' => 'Rouvrir la journée',
    ],
];
