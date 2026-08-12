<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : terminal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Terminaux de pointage',
    'intro' => 'Des terminaux RFID/NFC fixes permettent aux employés sans appareil professionnel de pointer l\'entrée et la sortie. Les événements alimentent la même logique de présence que les pointages navigateur (corrections, rapports). Les jetons d\'appareil et les identifiants de badge ne sont stockés que sous forme de hachage.',

    'new_heading' => 'URL d\'ingestion du terminal',
    'new_hint' => 'Saisissez-la dans le terminal maintenant — le jeton n\'est affiché qu\'une seule fois.',

    'terminals_heading' => 'Terminaux',
    'no_terminals' => 'Aucun terminal enregistré pour le moment.',
    'badges_heading' => 'Badges',
    'no_badges' => 'Aucun badge attribué pour le moment.',

    'field' => [
        'name' => 'Libellé',
        'name_placeholder' => 'p. ex. Hall Nord',
        'site' => 'Site',
        'no_site' => '— sans site —',
    ],

    'badge' => [
        'user' => 'Employé',
        'label' => 'Libellé',
        'uid' => 'Identifiant du badge',
        'uid_placeholder' => 'UID RFID/NFC',
        'uid_help' => 'Stocké uniquement sous forme de hachage (pas d\'identifiant en clair).',
        'validity' => 'Validité',
        'valid_from' => 'Valable à partir du',
        'valid_until' => 'Valable jusqu\'au',
        'outside_validity' => 'hors validité',
    ],

    'action' => [
        'register' => 'Enregistrer',
        'disable' => 'Désactiver',
        'assign' => 'Attribuer',
        'revoke' => 'Révoquer',
        'rotate' => 'Renouveler le jeton',
        'rotate_help' => 'Génère un nouveau jeton d\'appareil — l\'ancien devient immédiatement invalide.',
    ],

    'col' => [
        'status' => 'Statut',
        'status_display' => 'Affichage du statut',
        'last_seen' => 'Vu la dernière fois',
    ],

    'status_display' => [
        'on' => 'Activé',
        'off' => 'Désactivé',
        'help' => 'Affiche le solde/les congés restants sur l\'appareil après le pointage (visible par des tiers) — désactivé par défaut.',
    ],

    'buffer' => [
        'label' => 'Tampon',
        'help' => 'Événements hors ligne signalés par le terminal, pas encore transmis.',
    ],

    'status' => [
        'active' => 'Actif',
        'inactive' => 'Désactivé',
        'revoked' => 'Révoqué',
    ],

    'flash' => [
        'registered' => 'Terminal enregistré.',
        'terminal_disabled' => 'Terminal désactivé.',
        'badge_assigned' => 'Badge attribué.',
        'badge_revoked' => 'Badge révoqué.',
        'badge_taken' => 'Cet identifiant de badge est déjà attribué.',
        'token_rotated' => 'Jeton d\'appareil renouvelé — nouvelle URL d\'ingestion affichée une seule fois.',
        'status_enabled' => 'Affichage du statut activé.',
        'status_disabled' => 'Affichage du statut désactivé.',
    ],
];
