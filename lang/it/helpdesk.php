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
        'noCustomer' => 'senza cliente',
        'noProject' => 'senza progetto',
        'noAsset' => 'senza asset',
    ],
    'service' => [
        'label' => 'Servizio aziendale interessato',
        'none' => 'senza servizio aziendale',
        'offering' => 'Offerta di servizio',
        'noOffering' => 'senza offerta',
        'all' => 'Tutti i servizi aziendali',
    ],
    'map' => [
        'title' => 'Mappa dei servizi',
        'subtitle' => 'Quali servizi aziendali dipendono l’uno dall’altro e chi è coinvolto da un guasto.',
        'empty' => 'Nessun servizio aziendale nel catalogo per ora.',
        'service' => 'Servizio aziendale',
        'dependsOn' => 'Dipende da',
        'dependsOnHint' => 'Se uno di questi servizi aziendali si guasta, anche questo è coinvolto. Selezione multipla con Ctrl o Cmd.',
        'dependents' => 'Richiesto da',
        'affected' => 'Coinvolti da un guasto',
        'openTickets' => 'Ticket aperti',
        'cycle' => 'Questa dipendenza creerebbe un ciclo: un servizio aziendale selezionato dipende già da questo.',
    ],
    'impact' => [
        'title' => 'Impatto',
        'none' => 'Nessun altro servizio aziendale dipende da «:service».',
        'affected' => 'Se «:service» si guasta, sono coinvolti anche questi servizi aziendali:',
        'openTickets' => ':count ticket aperto|:count ticket aperti',
    ],
    'time' => [
        'title' => 'Tempi sul ticket',
        'total' => 'Totale',
        'billable' => 'fatturabile',
        'book' => 'Registra tempo',
        'empty' => 'Nessun tempo registrato su questo ticket per ora.',
        'needsProject' => 'Per registrare tempi il ticket deve avere un progetto.',
        'ticketOptional' => 'Ticket (facoltativo)',
        'noTicket' => 'Nessun ticket',
    ],
    'routing' => [
        'conditionsHint' => 'Chiavi: kind, priority, source, customer_id, queue_id, sla_contract_id, business_service_id, service_offering_id, locale (ad es. "en"), business_hours ("in" o "out"). Valori singoli o come lista.',
        'actionsHint' => 'Chiavi: set_queue, set_priority, set_sla, set_team, assign ("round_robin" a turno o "least_loaded" in base al carico, nel team della coda).',
    ],
    'report' => [
        'month' => 'Mese',
        'created' => 'Segnalati',
        'reactionP50' => 'Reazione P50 (h)',
        'resolutionP50' => 'Risoluzione P50 (h)',
        'trendCreated' => 'Ticket segnalati al mese',
        'trendResolution' => 'Tempo di risoluzione P50 al mese',
        'trendTable' => 'Andamento degli ultimi dodici mesi',
        'byService' => 'Ticket per servizio aziendale',
        'open' => 'Aperti',
        'breached' => 'Termine di risoluzione superato',
        'csvTrends' => 'CSV andamento',
        'csvServices' => 'CSV servizi aziendali',
    ],
    'portal' => [
        'asset' => 'Dispositivo interessato',
        'noAsset' => 'nessun dispositivo specifico',
        'service' => 'Servizio interessato',
        'noService' => 'nessun servizio specifico',
    ],
];
