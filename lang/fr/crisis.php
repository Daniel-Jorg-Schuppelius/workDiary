<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : crisis.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Offline-Krisenmappe (MVP-914).
    'offline' => [
        'save' => 'Enregistrer le dossier de crise hors ligne',
        'hint' => 'Enregistre les crises actives avec la situation, les mesures et les coordonnées de la cellule de crise sur cet appareil ; lisible sans connexion sur la page hors ligne. Supprimé à la déconnexion.',
    ],
    // Öffentliche Statusseite (MVP-915).
    'status_page' => [
        'title' => 'Page de statut (publique)',
        'intro' => 'La page de statut affiche sans connexion les messages de crise envoyés au public ; les messages aux clients apparaissent aussi dans le portail client. Seuls l\'objet, le texte et l\'heure sont affichés, jamais le dossier de crise.',
        'token_once' => 'Ce lien n\'est affiché que maintenant — il n\'est enregistré nulle part.',
        'state' => 'État',
        'state_none' => 'Non configuré',
        'state_active' => 'Accessible publiquement',
        'state_paused' => 'En pause',
        'hint' => 'Identifiant',
        'issued_at' => 'Émis le',
        'action' => [
            'issue' => 'Émettre le lien',
            'rotate' => 'Renouveler le lien',
            'revoke' => 'Révoquer l\'accès',
            'pause' => 'Mettre en pause',
            'resume' => 'Activer',
        ],
        'confirm' => [
            'rotate' => 'Émettre un nouveau lien ? L\'ancien lien cessera de fonctionner.',
            'revoke' => 'Révoquer l\'accès ? La page de statut ne sera plus accessible publiquement.',
        ],
        'flash' => [
            'issued' => 'Nouveau lien émis.',
            'revoked' => 'Accès révoqué.',
            'saved' => 'Enregistré.',
        ],
        'public_title' => 'Situation actuelle – :org',
        'public_intro' => 'Nous vous informons ici des perturbations et incidents en cours.',
        'all_clear' => 'Aucune perturbation n\'est signalée actuellement.',
        'resolved' => 'fin d\'alerte',
    ],
    // BIA-Register (MVP-943).
    'bia' => [
        'title' => 'Registre BIA',
        'subtitle' => 'Processus métier avec criticité, objectifs de reprise (RTO/RPO) et durée maximale d\'interruption admissible (MTPD).',
        'create' => 'Ajouter un processus',
        'edit' => 'Modifier le processus',
        'save' => 'Enregistrer',
        'empty' => 'Aucun processus dans le registre.',
        'inactive' => 'inactif',
        'import' => 'Reprendre depuis les registres',
        'import_hint' => 'Suggestions issues du registre des traitements, des risques SMSI et des modèles de procédure actifs. Seule votre sélection est reprise.',
        'import_submit' => 'Reprendre la sélection',
        'adopt' => 'Reprendre du registre BIA',
        'kind' => [
            'processing_activity' => 'Traitement',
            'isms_risk' => 'Risque SMSI',
            'procedure_template' => 'Modèle de procédure',
        ],
        'criticality' => [
            'low' => 'faible',
            'medium' => 'moyenne',
            'high' => 'élevée',
            'critical' => 'critique',
        ],
        'field' => [
            'name' => 'Processus',
            'description' => 'Description',
            'criticality' => 'Criticité',
            'rto_hours' => 'RTO (heures)',
            'rpo_hours' => 'RPO (heures)',
            'mtpd_hours' => 'MTPD (heures)',
            'owner' => 'Responsable',
            'dependencies' => 'Dépendances (systèmes, fournisseurs, personnes)',
            'review_due_on' => 'Revue prévue',
            'is_active' => 'Actif',
        ],
        'flash' => [
            'saved' => 'Processus enregistré.',
            'imported' => ':count processus repris.',
            'adopted' => 'Processus repris du registre BIA.',
        ],
    ],
    // BCM-Auswertung (MVP-944).
    'bcm_report' => [
        'title' => 'Rapport PCA',
        'subtitle' => 'Indicateurs selon ISO 22301 : exercices, actions, retours d\'expérience et état BIA.',
        'back' => 'Gestion de crise',
        'disclaimer' => 'Indicateurs issus des données saisies ; aucune déclaration sur la certifiabilité.',
        'overdue' => ':count en retard',
        'row' => [
            'exercises' => 'Exercices depuis le :date',
            'effectiveness' => 'Efficacité',
            'exercises_due' => 'Exercices dus',
            'actions_open' => 'Actions ouvertes',
            'reviews' => 'Crises terminées avec retour d\'expérience',
            'processes' => 'Processus dans le registre BIA',
            'without_rto' => 'Processus sans RTO',
            'review_due' => 'Processus à réviser',
        ],
        'effectiveness' => [
            'effective' => 'efficace',
            'partly' => 'partiellement',
            'ineffective' => 'inefficace',
            'open' => 'non évalué',
        ],
    ],
];
