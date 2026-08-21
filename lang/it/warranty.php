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
    'title' => 'Garanzie',
    'subtitle' => 'Responsabilità propria e termini esigibili verso i subappaltatori affiancati',
    'empty' => 'Nessun termine di garanzia registrato.',
    'overridden' => '(derogato)',
    'created' => 'Termine di garanzia registrato.',
    'closed' => 'Termine chiuso.',
    'dialog_hint' => 'Senza una data di fine propria deriva dal fondamento giuridico. Il termine decorre dal giorno del collaudo — non dalla fattura né dal completamento.',
    'override_reason' => 'Motivo di una data di fine derogatoria',
    'override_reason_hint' => 'Obbligatorio non appena la data di fine si discosta dal fondamento giuridico.',
    'custom_needs_end' => 'Un termine liberamente pattuito richiede una data di fine.',
    'end_before_start' => 'La fine deve essere successiva all’inizio.',
    'override_needs_reason' => 'Una fine derogatoria richiede una motivazione.',
    'not_open' => 'Questo termine non è più aperto.',
    'action' => [
        'create' => 'Registra termine',
        'close' => 'Chiudi',
    ],
    'kpi' => [
        'owed' => 'Responsabilità propria',
        'owed_hint' => 'Termini dovuti al committente.',
        'claimable' => 'Esigibili',
        'claimable_hint' => 'Termini verso i subappaltatori.',
        'expiring' => 'In scadenza entro 6 mesi',
        'critical' => 'Il termine del subappaltatore scade per primo',
        'critical_hint' => 'Dopo si risponde da soli di un difetto causato da altri.',
    ],
    'critical' => [
        'heading' => 'Termini dei subappaltatori scadono prima della responsabilità propria',
        'hint' => 'Verificare ora e, nel dubbio, contestare — poi il diritto verso il subappaltatore è perso mentre la responsabilità propria continua.',
    ],
    'column' => [
        'side' => 'Lato',
        'project' => 'Progetto',
        'party' => 'Controparte',
        'trade' => 'Categoria',
        'basis' => 'Fondamento',
        'starts_on' => 'Inizio',
        'ends_on' => 'Fine',
        'status' => 'Stato',
        'protocol' => 'Verbale di collaudo',
        'customer' => 'Cliente',
        'supplier' => 'Subappaltatore',
        'responsible' => 'Responsabile',
        'note' => 'Nota',
    ],
    'filter' => [
        'side' => 'Lato',
        'status' => 'Stato',
    ],
];
