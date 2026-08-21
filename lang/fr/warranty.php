<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : warranty.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Gewährleistungsfristen (Feature 115, MVP-604).
return [
    'title' => 'Garanties',
    'subtitle' => 'Responsabilité propre et délais opposables aux sous-traitants côte à côte',
    'empty' => 'Aucun délai de garantie enregistré.',
    'overridden' => '(dérogatoire)',
    'created' => 'Délai de garantie enregistré.',
    'closed' => 'Délai clôturé.',
    'dialog_hint' => 'Sans date de fin propre, elle découle du fondement juridique. Le délai commence le jour de la réception — pas à la facturation ni à l’achèvement.',
    'override_reason' => 'Motif d’une date de fin dérogatoire',
    'override_reason_hint' => 'Obligatoire dès que la date de fin s’écarte du fondement juridique.',
    'custom_needs_end' => 'Un délai librement convenu exige une date de fin.',
    'end_before_start' => 'La fin doit être postérieure au début.',
    'override_needs_reason' => 'Une fin dérogatoire exige un motif.',
    'not_open' => 'Ce délai n’est plus ouvert.',
    'action' => [
        'create' => 'Enregistrer un délai',
        'close' => 'Clôturer',
    ],
    'kpi' => [
        'owed' => 'Responsabilité propre',
        'owed_hint' => 'Délais dus au maître d’ouvrage.',
        'claimable' => 'Opposables',
        'claimable_hint' => 'Délais envers les sous-traitants.',
        'expiring' => 'Expire sous 6 mois',
        'critical' => 'Le délai du sous-traitant finit en premier',
        'critical_hint' => 'Ensuite on répond seul d’un défaut causé par un autre.',
    ],
    'critical' => [
        'heading' => 'Des délais de sous-traitants finissent avant votre propre responsabilité',
        'hint' => 'Vérifier maintenant et dénoncer en cas de doute — ensuite le recours contre le sous-traitant est perdu alors que votre responsabilité continue.',
    ],
    'column' => [
        'side' => 'Côté',
        'project' => 'Projet',
        'party' => 'Contrepartie',
        'trade' => 'Corps d’état',
        'basis' => 'Fondement',
        'starts_on' => 'Début',
        'ends_on' => 'Fin',
        'status' => 'Statut',
        'protocol' => 'PV de réception',
        'customer' => 'Client',
        'supplier' => 'Sous-traitant',
        'responsible' => 'Responsable',
        'note' => 'Note',
    ],
    'filter' => [
        'side' => 'Côté',
        'status' => 'Statut',
    ],
];
