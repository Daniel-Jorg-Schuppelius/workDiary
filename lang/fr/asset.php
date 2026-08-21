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
        'empty' => 'Aucune pièce enregistrée.',
        'saved' => 'Pièce enregistrée.',
        'replaced' => 'Pièce remplacée — l’ancienne reste dans l’historique.',
        'removed' => 'Pièce démontée.',
        'not_installed' => 'Cette pièce n’est plus montée.',
        'foreign_organization' => 'La consommation de matériel et l’appareil appartiennent à des organisations différentes.',
        'replace_hint' => '« :name » est démontée et reste dans l’historique avec sa date de dépose.',
        'label_hint' => 'Pour les pièces tierces sans fiche article.',
        'interval_hint' => 'Intervalle de remplacement en mois — l’échéance en découle.',
        'action' => [
            'add' => 'Ajouter une pièce',
            'replace' => 'Remplacer',
            'remove' => 'Démonter',
        ],
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
];
