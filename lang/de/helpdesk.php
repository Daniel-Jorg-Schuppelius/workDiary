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
        'noCustomer' => 'ohne Kunde',
        'noProject' => 'ohne Projekt',
        'noAsset' => 'ohne Asset',
    ],
    'service' => [
        'label' => 'Betroffener Fachdienst',
        'none' => 'ohne Fachdienst',
        'offering' => 'Serviceangebot',
        'noOffering' => 'ohne Angebot',
        'all' => 'Alle Fachdienste',
    ],
    'map' => [
        'title' => 'Servicelandkarte',
        'subtitle' => 'Welche Fachdienste voneinander abhängen und wer bei einem Ausfall mitbetroffen ist.',
        'empty' => 'Noch keine Fachdienste im Servicekatalog.',
        'service' => 'Fachdienst',
        'dependsOn' => 'Hängt ab von',
        'dependsOnHint' => 'Fällt einer dieser Fachdienste aus, ist dieser mitbetroffen. Mehrfachauswahl mit Strg bzw. Cmd.',
        'dependents' => 'Wird benötigt von',
        'affected' => 'Betroffen bei Ausfall',
        'openTickets' => 'Offene Tickets',
        'cycle' => 'Diese Abhängigkeit ergäbe einen Kreis: ein gewählter Fachdienst hängt bereits von diesem ab.',
    ],
    'impact' => [
        'title' => 'Auswirkung',
        'none' => 'Kein anderer Fachdienst hängt von „:service“ ab.',
        'affected' => 'Fällt „:service“ aus, sind auch diese Fachdienste betroffen:',
        'openTickets' => ':count offenes Ticket|:count offene Tickets',
    ],
    'time' => [
        'title' => 'Zeiten am Ticket',
        'total' => 'Gesamt',
        'billable' => 'abrechenbar',
        'book' => 'Zeit buchen',
        'empty' => 'Noch keine Zeiten auf dieses Ticket gebucht.',
        'needsProject' => 'Zum Buchen von Zeiten braucht das Ticket ein Projekt.',
        'ticketOptional' => 'Ticket (optional)',
        'noTicket' => 'Kein Ticket',
    ],
    'routing' => [
        'conditionsHint' => 'Schlüssel: kind, priority, source, customer_id, queue_id, sla_contract_id, business_service_id, service_offering_id, locale (z. B. "en"), business_hours ("in" oder "out"). Werte einzeln oder als Liste.',
        'actionsHint' => 'Schlüssel: set_queue, set_priority, set_sla, set_team, assign ("round_robin" reihum oder "least_loaded" nach Auslastung, im Team der Queue).',
    ],
    'report' => [
        'month' => 'Monat',
        'created' => 'Gemeldet',
        'reactionP50' => 'Reaktion P50 (h)',
        'resolutionP50' => 'Lösung P50 (h)',
        'trendCreated' => 'Gemeldete Tickets je Monat',
        'trendResolution' => 'Lösungszeit P50 je Monat',
        'trendTable' => 'Verlauf der letzten zwölf Monate',
        'byService' => 'Tickets je Fachdienst',
        'open' => 'Offen',
        'breached' => 'Lösungsfrist verletzt',
        'csvTrends' => 'CSV Verlauf',
        'csvServices' => 'CSV Fachdienste',
    ],
    'portal' => [
        'asset' => 'Betroffenes Gerät',
        'noAsset' => 'kein bestimmtes Gerät',
        'service' => 'Betroffener Service',
        'noService' => 'kein bestimmter Service',
    ],
];
