<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : helpdesk.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Helpdesk und Service Desk (Feature 065, Phase 156).
return [
    'form' => [
        'noCustomer' => 'sans client',
        'noProject' => 'sans projet',
        'noAsset' => 'sans actif',
    ],
    'service' => [
        'label' => 'Service métier concerné',
        'none' => 'sans service métier',
        'offering' => 'Offre de service',
        'noOffering' => 'sans offre',
        'all' => 'Tous les services métier',
    ],
    'map' => [
        'title' => 'Cartographie des services',
        'subtitle' => 'Quels services métier dépendent les uns des autres et qui est touché par une panne.',
        'empty' => 'Aucun service métier dans le catalogue pour l’instant.',
        'service' => 'Service métier',
        'dependsOn' => 'Dépend de',
        'dependsOnHint' => 'Si l’un de ces services métier tombe en panne, celui-ci est également touché. Sélection multiple avec Ctrl ou Cmd.',
        'dependents' => 'Requis par',
        'affected' => 'Touchés par une panne',
        'openTickets' => 'Tickets ouverts',
        'cycle' => 'Cette dépendance créerait un cycle : un service métier sélectionné dépend déjà de celui-ci.',
    ],
    'impact' => [
        'title' => 'Impact',
        'none' => 'Aucun autre service métier ne dépend de « :service ».',
        'affected' => 'Si « :service » tombe en panne, ces services métier sont également touchés :',
        'openTickets' => ':count ticket ouvert|:count tickets ouverts',
    ],
    'time' => [
        'title' => 'Temps sur ce ticket',
        'total' => 'Total',
        'billable' => 'facturable',
        'book' => 'Saisir du temps',
        'empty' => 'Aucun temps saisi sur ce ticket pour l’instant.',
        'needsProject' => 'Le ticket doit être rattaché à un projet pour saisir du temps.',
        'ticketOptional' => 'Ticket (facultatif)',
        'noTicket' => 'Aucun ticket',
    ],
    'routing' => [
        'conditionsHint' => 'Clés : kind, priority, source, customer_id, queue_id, sla_contract_id, business_service_id, service_offering_id, locale (p. ex. "en"), business_hours ("in" ou "out"). Valeur unique ou liste.',
        'actionsHint' => 'Clés : set_queue, set_priority, set_sla, set_team, assign ("round_robin" à tour de rôle ou "least_loaded" selon la charge, dans l’équipe de la file).',
    ],
    'report' => [
        'month' => 'Mois',
        'created' => 'Signalés',
        'reactionP50' => 'Réaction P50 (h)',
        'resolutionP50' => 'Résolution P50 (h)',
        'trendCreated' => 'Tickets signalés par mois',
        'trendResolution' => 'Temps de résolution P50 par mois',
        'trendTable' => 'Évolution sur les douze derniers mois',
        'byService' => 'Tickets par service métier',
        'open' => 'Ouverts',
        'breached' => 'Délai de résolution dépassé',
        'csvTrends' => 'CSV évolution',
        'csvServices' => 'CSV services métier',
    ],
    'portal' => [
        'asset' => 'Appareil concerné',
        'noAsset' => 'aucun appareil précis',
        'service' => 'Service concerné',
        'noService' => 'aucun service précis',
    ],
];
