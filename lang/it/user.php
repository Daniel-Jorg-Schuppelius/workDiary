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
