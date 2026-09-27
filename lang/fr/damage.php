<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : damage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Schadensfälle (MVP-919/920).
return [
    'title' => 'Sinistres',
    'subtitle' => 'Sinistres et dossiers d\'assurance liés à la location, au leasing, aux réclamations et aux véhicules — avec règlement, franchise et historique.',
    'case' => 'Sinistre',
    'empty' => 'Aucun sinistre — ils sont créés depuis le dossier (location, leasing, réclamation, véhicule).',
    'nav' => [
        'section' => 'Sinistres et rappels',
        'cases' => 'Sinistres',
    ],
    'kpi' => [
        'open' => 'Dossiers ouverts',
        'open_estimate' => 'Estimé (ouverts)',
        'settled' => 'Réglé',
    ],
    'filter' => [
        'all_status' => 'Tous les statuts',
        'all_subjects' => 'Tous les dossiers',
        'all_kinds' => 'Tous les types',
    ],
    'field' => [
        'number' => 'Numéro',
        'title' => 'Intitulé',
        'subject' => 'Dossier lié',
        'kind' => 'Type de sinistre',
        'status' => 'Statut',
        'estimated_amount' => 'Dommage estimé',
        'settled_amount' => 'Montant réglé',
        'deductible_amount' => 'Franchise',
        'net_recovery' => 'Remboursement après franchise',
        'currency' => 'Devise',
        'occurred_at' => 'Date du sinistre',
        'reported_at' => 'Déclaré le',
        'insurer_name' => 'Assureur',
        'policy_number' => 'Numéro de police',
        'claim_number' => 'Numéro de sinistre',
        'responsible_user_id' => 'Responsable',
        'description' => 'Déroulement',
    ],
    'section' => [
        'case' => 'Sinistre',
        'insurance' => 'Assurance',
        'amounts' => 'Montants',
        'status' => 'Changer le statut',
        'journal' => 'Historique',
    ],
    'action' => [
        'show' => 'Afficher',
        'edit' => 'Modifier',
        'save' => 'Enregistrer',
        'open' => 'Créer le sinistre',
        'report' => 'Déclarer un sinistre',
    ],
    'dialog' => [
        'create' => 'Déclarer un sinistre',
        'edit' => 'Modifier le sinistre',
    ],
    'card' => [
        'title' => 'Sinistres',
        'none' => 'Aucun sinistre.',
    ],
    'status' => [
        'reported' => 'Déclaré',
        'submitted' => 'Transmis à l\'assureur',
        'in_review' => 'En cours d\'examen',
        'settled' => 'Réglé',
        'rejected' => 'Refusé',
        'closed' => 'Clôturé',
    ],
    'transition' => [
        'submitted' => 'Transmettre à l\'assureur',
        'in_review' => 'Marquer en examen',
        'settled' => 'Enregistrer le règlement',
        'rejected' => 'Enregistrer le refus',
        'closed' => 'Clôturer',
    ],
    'kind' => [
        'property' => 'Dommage matériel',
        'liability' => 'Responsabilité civile',
        'theft' => 'Vol/perte',
        'vehicle' => 'Dommage au véhicule',
        'transport' => 'Dommage de transport',
        'other' => 'Autre',
    ],
    'error' => [
        'settled_amount_required' => 'Le montant réglé est requis pour « réglé ».',
    ],
    'flash' => [
        'opened' => 'Sinistre :number créé.',
        'saved' => 'Sinistre enregistré.',
        'status' => 'Statut : :status.',
    ],
    'subject_type' => [
        'rental_cases' => 'Location',
        'asset_finance_contracts' => 'Contrat de leasing/financement',
        'claim_cases' => 'Réclamation',
        'vehicles' => 'Véhicule',
    ],
];
