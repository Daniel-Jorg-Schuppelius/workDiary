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
    'kiosk' => [
        'pin_toggle' => 'Badge oublié ? Pointer avec le PIN',
        'pin_submit' => 'Pointer',
        'heading' => 'Adresse du kiosque',
        'hint' => 'À ouvrir dans le navigateur d\'une tablette — la tablette devient une pointeuse. Contient le même jeton ; affichée une seule fois.',
        'title' => 'Pointeuse',
        'intro' => 'Présentez votre badge au lecteur.',
        'mode' => 'Type de pointage',
        'mode_work' => 'Arrivée / Départ',
        'mode_break' => 'Pause',
        'badge_label' => 'Badge',
        'nfc_start' => 'Utiliser le NFC de cet appareil',
        'nfc_active' => 'NFC en lecture',
        'flex_balance' => 'Horaire variable :',
        'status' => [
            'invalid_pin' => 'Matricule ou PIN invalide',
            'clocked_in' => 'Arrivée enregistrée',
            'clocked_out' => 'Départ enregistré',
            'break_started' => 'Pause commencée',
            'break_ended' => 'Pause terminée',
            'noop' => 'Aucune présence ouverte',
            'skipped' => 'Déjà enregistré',
            'unknown_badge' => 'Badge inconnu',
            'rejected' => 'Pointage refusé',
            'invalid_token' => 'Terminal désactivé',
            'unavailable' => 'Pointage impossible pour le moment',
            'network' => 'Pas de connexion — veuillez réessayer',
            'nfc_unavailable' => 'Le NFC n\'est pas disponible sur cet appareil',
            'error' => 'Erreur de pointage',
        ],
    ],
    'checkpoint' => [
        'heading' => 'Points de check-in (QR/NFC)',
        'intro' => 'Un code sur le site ou le véhicule : le personnel le scanne avec son propre appareil et pointe en étant connecté. La même adresse peut être écrite sur un autocollant NFC.',
        'empty' => 'Aucun point de check-in.',
        'action' => [
            'create' => 'Créer un point de check-in',
            'qr' => 'Imprimer le QR code',
            'enable' => 'Activer',
        ],
        'field' => [
            'kind' => 'Type',
            'location' => 'Site / véhicule',
            'vehicle' => 'Véhicule',
            'radius' => 'Rayon (m)',
            'location_check' => 'Vérification du lieu (facultative)',
            'latitude' => 'Latitude',
            'longitude' => 'Longitude',
        ],
        'help' => [
            'site' => 'Uniquement pour le type « Site ».',
            'vehicle' => 'Obligatoire pour le type « Véhicule ».',
            'location_check' => 'Un code peut être photographié. Avec un rayon, l\'appareil doit être à proximité lors du pointage ; sans coordonnées propres, celles du site s\'appliquent. La position n\'est pas enregistrée.',
        ],
        'error' => [
            'radius_without_center' => 'Le rayon nécessite un emplacement : saisissez des coordonnées ou choisissez un site géolocalisé.',
            'vehicle' => 'Le véhicule est introuvable.',
        ],
        'flash' => [
            'created' => 'Point de check-in créé.',
            'enabled' => 'Point de check-in activé.',
            'disabled' => 'Point de check-in désactivé.',
        ],
        'qr' => [
            'alt' => 'QR code du check-in « :name »',
            'hint' => 'Scannez avec votre téléphone, connectez-vous et confirmez l\'arrivée ou le départ.',
            'nfc_hint' => 'Pour un autocollant NFC, écrivez cette adresse comme adresse web (URL) avec une application NFC.',
        ],
    ],
    'pin' => [
        'heading' => 'Codes PIN du terminal',
        'intro' => 'Badge oublié ? Avec le matricule et le PIN, le pointage reste possible au terminal et au kiosque. Seul un hachage est enregistré ; après 5 échecs, le PIN est bloqué 15 minutes.',
        'empty' => 'Aucun PIN attribué.',
        'action' => [
            'set' => 'Définir le PIN',
            'unlock' => 'Débloquer',
            'remove' => 'Supprimer',
        ],
        'field' => [
            'pin' => 'PIN',
            'pin_confirmation' => 'Répéter le PIN',
            'personnel_number' => 'Matricule',
        ],
        'help' => [
            'dialog' => '4 à 8 chiffres. La personne apprend le PIN par vous — il n\'est plus consultable ensuite.',
            'personnel_number' => 'Uniquement les personnes avec matricule — c\'est la seconde partie de l\'identification au terminal.',
        ],
        'status' => [
            'locked_until' => 'bloqué jusqu\'à :time',
        ],
        'confirm' => [
            'remove' => 'Vraiment supprimer le PIN ? La personne ne pourra plus pointer qu\'avec son badge.',
        ],
        'error' => [
            'format' => 'Le PIN doit comporter de :min à :max chiffres.',
            'personnel_number' => 'La personne n\'a pas de matricule — sans lui, le PIN est inutilisable au terminal.',
        ],
        'flash' => [
            'set' => 'PIN défini.',
            'unlocked' => 'PIN débloqué.',
            'removed' => 'PIN supprimé.',
        ],
    ],
];
