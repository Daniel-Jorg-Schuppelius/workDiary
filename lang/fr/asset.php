<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : asset.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'lifecycle' => [
        'in_operation' => 'En service',
        'retired' => 'Remplacé',
        'decommissioned' => 'Mis hors service',
    ],
    'dossier' => [
        'title' => 'Dossier d\'objet',
        'back' => 'Retour à l\'actif',
        'generated_at' => 'Généré le',
        'lifecycle' => 'Cycle de vie',
        'master_data' => 'Données de base',
        'health' => 'État',
        'commissioned' => 'Mise en service',
        'decommissioned' => 'Mise hors service',
        'warranty' => 'Garantie jusqu\'au',
        'warranty_expired' => 'expirée',
        'in_service_days' => 'En service (jours)',
        'room_requirements' => 'Exigences liées au local',
        'maintenance' => 'Maintenances',
        'next_due' => 'Prochaine échéance',
        'last_run' => 'Dernière réalisation',
        'due' => 'Échue',
        'scheduled' => 'Planifiée',
        'assignments' => 'Sorties / retours',
        'checked_out' => 'Sorti',
        'assignee' => 'Bénéficiaire',
        'returned' => 'Retourné',
        'open' => 'Ouvert',
        'defects' => 'Défauts / blocages',
        'blocks' => 'Bloque',
        'orders' => 'Ordres',
        'timeline' => 'Historique du cycle de vie',
        'event' => [
            'asset.audit' => 'Événement actif',
            'order.linked' => 'Ordre lié',
            'protocol.linked' => 'Protocole lié',
            'material.linked' => 'Utilisation de matériel liée',
            'attachment.linked' => 'Pièce jointe ajoutée',
            'assignment.checkedOut' => 'Sorti',
            'assignment.returned' => 'Retourné',
            'defect.reported' => 'Défaut signalé',
            'defect.resolved' => 'Défaut résolu',
            'maintenance.completed' => 'Maintenance réalisée',
            'unknown' => 'Événement',
        ],
    ],
    // Anlagen-Stückliste (Feature 118, MVP-607).
    'components' => [
        'title' => 'Nomenclature',
        'stock_serial_none' => 'aucun (pièce tierce)',
        'stock_serial_hint' => 'Seules les pièces de votre propre stock en ont un.',
        'serial_no_hint' => 'Texte libre ; écrasé si un numéro de série du stock est lié.',
        'empty' => 'Aucune pièce enregistrée.',
        'saved' => 'Pièce enregistrée.',
        'replaced' => 'Pièce remplacée — l’ancienne reste dans l’historique.',
        'removed' => 'Pièce démontée.',
        'not_installed' => 'Cette pièce n’est plus montée.',
        'foreign_organization' => 'La consommation de matériel et l’appareil appartiennent à des organisations différentes.',
        'usage_of_other_asset' => 'La consommation de matériel concerne un autre appareil.',
        'already_adopted' => 'Cette consommation de matériel figure déjà dans la nomenclature.',
        'adopted' => 'Matériel repris dans la nomenclature.',
        'replace_hint' => '« :name » est démontée et reste dans l’historique avec sa date de dépose.',
        'label_hint' => 'Pour les pièces tierces sans fiche article.',
        'interval_hint' => 'Intervalle de remplacement en mois — l’échéance en découle.',
        'action' => [
            'add' => 'Ajouter une pièce',
            'replace' => 'Remplacer',
            'remove' => 'Démonter',
            'adopt' => 'Reprendre',
        ],
        'adopt' => ['heading' => 'Matériel des interventions', 'hint' => 'Matériel affecté à cet appareil sur une feuille de temps. « Reprendre » l’inscrit dans la nomenclature avec le jour de l’intervention comme date de montage.', 'column_date' => 'Intervention'],
        'due' => ['heading' => 'Pièces d’usure à remplacer', 'hint' => 'Proposition pour la prochaine intervention — le technicien décide.'],
        'history' => ['heading' => 'Historique (pièces déposées et remplacées)'],
        'column' => [
            'name' => 'Pièce',
            'article' => 'Article',
            'label' => 'Désignation (texte libre)',
            'position' => 'Emplacement',
            'quantity' => 'Quantité',
            'unit' => 'Unité',
            'serial_no' => 'Numéro de série',
            'stock_serial' => 'Numéro de série du stock',
            'installed_on' => 'Montée le',
            'removed_on' => 'Déposée le',
            'due_on' => 'Remplacement dû',
            'interval' => 'Intervalle (mois)',
            'status' => 'Statut',
            'note' => 'Note',
        ],
        'status' => [
            'installed' => 'Montée',
            'removed' => 'Déposée',
            'replaced' => 'Remplacée',
        ],
    ],
    'label' => [
        'print' => 'Étiquette avec QR',
    ],
    'nfc' => [
        'read' => 'Lire le tag NFC',
        'write' => 'Écrire le tag NFC',
    ],
    // Zustand, Außerbetriebnahme, Eigentümerwechsel, Rücknahme (MVP-1574).
    'health' => [
        'label' => 'État',
        'hint' => 'Déduit : critique en cas de blocage actif ou de défaut bloquant, dégradé en cas de défaut ouvert ou de maintenance/contrôle en retard.',
    ],
    'decommission' => [
        'title' => 'Mettre la ressource hors service',
        'action' => 'Mettre hors service',
        'intro' => 'La ressource est mise hors service définitivement. Elle ne peut ensuite plus être remise, louée, réservée ni planifiée ; son dossier est conservé.',
        'date' => 'Hors service depuis le',
        'reason' => 'Motif',
        'since' => 'Hors service depuis le :date',
        'by_disposal' => 'Éliminée par un ordre d\'élimination.',
        'done' => 'Ressource mise hors service.',
        'plans' => 'Plans de maintenance actifs : :count — ils seront mis en pause.',
        'duties' => 'Obligations de contrôle actives : :count — elles sont suspendues, les rendez-vous de contrôle ouverts sont annulés.',
        'error' => [
            'checked_out' => 'La ressource est encore remise. Veuillez d\'abord la reprendre.',
            'not_allowed' => 'La ressource ne peut pas être mise hors service depuis le statut « :status ».',
        ],
    ],
    // Wiederinbetriebnahme (MVP-1628)
    'recommission' => [
        'title' => 'Remettre l’équipement en service',
        'action' => 'Remettre en service',
        'intro' => 'L’équipement redevient actif : il peut être remis, prêté, réservé et planifié. Les plans de maintenance suspendus et les obligations de contrôle en sommeil restent désactivés — réactivez-les un par un.',
        'reason' => 'Motif de la remise en service',
        'done' => 'Équipement remis en service.',
        'error' => [
            'not_decommissioned' => 'Seul un équipement mis hors service peut être remis en service.',
            'disposed' => 'L’équipement a été éliminé via un ordre d’élimination et reste hors service.',
        ],
    ],
    'ownership' => [
        'title' => 'Changer de propriétaire',
        'action' => 'Changer de propriétaire',
        'current' => 'Actuellement : :owner',
        'owned_by' => 'Propriété',
        'customer_hint' => 'Uniquement pour la propriété « Client » ; sinon le lien client est supprimé.',
        'note' => 'Note sur le changement de propriétaire',
        'note_hint' => 'Enregistrée uniquement si le client change ; le changement figure alors dans le dossier.',
        'done' => 'Changement de propriétaire enregistré.',
        'error' => [
            'unchanged' => 'La propriété et le client sont inchangés.',
            'forbidden' => 'Un autre client constitue un changement de propriétaire ; vous n\'en avez pas le droit.',
        ],
    ],
    'return' => [
        'title' => 'Reprendre la ressource',
        'condition_in' => 'État au retour',
        'condition_placeholder' => 'p. ex. complet, boîtier rayé',
    ],
    'timeline' => [
        'ownership_changed' => 'Propriétaire changé',
        'health_changed' => 'État modifié',
        'blocked' => 'Bloquée',
        'unblocked' => 'Blocage levé',
    ],
    'block' => [
        'from_status' => 'Issu du statut « :status ».',
        'status_left' => 'Statut modifié.',
    ],
    'guard' => [
        'out_of_service' => ':asset n\'est plus en service (:status) et ne peut pas être utilisée.',
    ],
    // Anschaffung an der Asset-Akte (MVP-1606).
    'acquisition' => [
        'title' => 'Acquisition',
        'supplier' => 'Acquis auprès de',
        'date' => 'Date d’acquisition',
        'cost' => 'Coût d’acquisition',
    ],
];
