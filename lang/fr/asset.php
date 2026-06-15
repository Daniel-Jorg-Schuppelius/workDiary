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
];
