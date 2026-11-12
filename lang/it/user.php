<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : user.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'employees' => 'Dipendenti',

    // Tariffa di fatturazione e di costo per persona (MVP-1565), visibile solo con user.payroll.manage.
    'rates' => [
        'legend' => 'Tariffe per fatturazione e consuntivo',
        'hint' => 'Riservato: visibile e modificabile solo con il permesso «Gestisci i dati del personale e delle buste paga». Le modifiche valgono per i tempi registrati o ricalcolati in seguito e vengono registrate nel log.',
        'hourly_rate' => 'Tariffa oraria (fatturazione)',
        'hourly_rate_hint' => 'Prevale sulla tariffa di attività, progetto e cliente; solo le condizioni particolari e una tariffa sulla registrazione del tempo hanno la precedenza. Lasci vuoto se deve valere la tariffa del progetto o del cliente.',
        'internal_rate' => 'Tariffa di costo interna',
        'internal_rate_hint' => 'Costo orario di questa persona per il consuntivo; prevale sulla tariffa di costo di attività, progetto e cliente.',
    ],

    'role' => [
        'admin' => 'Amministratore',
        'meldestelle' => 'Ufficio segnalazioni',
        'datenschutz' => 'Protezione dei dati',
        'geschaeftsfuehrung' => 'Direzione',
        'personalverwaltung' => 'Gestione del personale',
        'teamleitung' => 'Capo team',
        'buchhaltung' => 'Contabilità',
        'user' => 'Dipendente',
        'aussendienst' => 'Servizio esterno',
        'callcenter' => 'Call center',
        'support' => 'Supporto',
        'training_manager' => 'Responsabile della formazione',
        'kunde' => 'Cliente',
    ],
    'employment_type' => [
        'vollzeit' => 'Tempo pieno',
        'teilzeit' => 'Part-time',
        'minijob' => 'Mini-job (marginale)',
        'midijob' => 'Midi-job (zona di transizione)',
        'kurzfristig' => 'Lavoro a breve termine',
        'werkstudent' => 'Studente lavoratore',
        'azubi' => 'Apprendista',
    ],
    'compensation_model' => [
        'payroll' => 'Interno (busta paga)',
        'pauschal' => 'Forfettario',
        'nach_zeitaufwand' => 'A consuntivo',
    ],
    'flat_interval' => [
        'monatlich' => 'Mensile',
        'pro_einsatz' => 'Per intervento',
        'einmalig' => 'Una tantum',
    ],
];
