<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : safety.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Événements de sécurité',
    ],
    'subtitle' => [
        'index' => 'Enregistrer et suivre les accidents, presqu’accidents, dangers et défauts.',
    ],
    'empty' => 'Aucun événement de sécurité enregistré.',

    'field' => [
        'event_no' => 'N°',
        'kind' => 'Type',
        'severity' => 'Gravité',
        'status' => 'Statut',
        'occurred_at' => 'Survenu le',
        'location' => 'Lieu',
        'affected_person' => 'Personne concernée',
        'reporter' => 'Signalé par',
        'subject' => 'Lié à',
        'description' => 'Description',
        'immediate_action' => 'Mesure immédiate',
        'root_cause' => 'Analyse des causes',
        'closed_at' => 'Clôturé le',
        'closed_by' => 'Clôturé par',
        'followup_title' => 'Titre de la mesure de suivi',
        'followup_description' => 'Description (facultatif)',
    ],

    'section' => [
        'status' => 'Changer le statut',
        'followup' => 'Mesure de suivi',
        'attachments' => 'Pièces jointes',
        'followups' => 'Mesures de suivi',
    ],

    'no_attachments' => 'Aucune pièce jointe.',
    'no_followups' => 'Aucune mesure de suivi pour le moment.',

    'action' => [
        'create' => 'Signaler un événement',
        'edit' => 'Modifier',
        'save' => 'Enregistrer',
        'show' => 'Voir',
        'back' => 'Retour',
        'create_followup' => 'Créer une mesure de suivi',
    ],

    'transition' => [
        'investigating' => 'Lancer l’investigation',
        'measuresDefined' => 'Mesures définies',
        'closed' => 'Clôturer',
    ],

    'hint' => [
        'root_cause_for_close' => 'Une analyse des causes est requise pour clôturer l’événement.',
        'followup' => 'Crée un point ouvert comme reprise lié à cet événement.',
    ],

    'flash' => [
        'created' => 'Événement de sécurité enregistré.',
        'updated' => 'Événement de sécurité mis à jour.',
        'deleted' => 'Événement de sécurité supprimé.',
        'followup_created' => 'Mesure de suivi créée.',
        'status' => [
            'reported' => 'Événement réinitialisé.',
            'investigating' => 'Investigation lancée.',
            'measuresDefined' => 'Mesures définies.',
            'closed' => 'Événement clôturé.',
        ],
    ],

    'error' => [
        'invalid_transition' => 'Changement de statut invalide : :from → :to.',
        'close_requires_root_cause' => 'La clôture nécessite une analyse des causes.',
    ],

    'report' => [
        'title' => 'Analyse de sécurité',
        'nav' => 'Sécurité au travail',
        'subtitle' => 'Événements de sécurité par type et gravité sur la période.',
        'by_kind' => 'Par type',
        'by_severity' => 'Par gravité',
        'kpi' => [
            'total' => 'Total des événements',
            'open' => 'Ouverts',
            'closed' => 'Clôturés',
            'critical' => 'Critiques',
        ],
    ],
];
