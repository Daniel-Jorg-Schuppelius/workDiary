<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : survey.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Umfragen (Feature 090, Phase 156).
return [
    'trend' => [
        'title' => 'NPS al mese',
        'unit' => 'NPS',
    ],
    'triggers' => [
        'title' => 'Attivatori',
        'order' => 'Invita automaticamente alla chiusura di un incarico',
        'course' => 'Invita automaticamente al completamento di un corso',
        'schedule' => 'Invita a intervallo fisso (giorni)',
        'scheduleHint' => 'Clienti con un incarico concluso o un ticket risolto nell’intervallo, al massimo una volta per intervallo. Vuoto = disattivato.',
        'saved' => 'Attivatori salvati.',
    ],
];
