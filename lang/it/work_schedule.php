<?php
/*
 * Created on   : Fri Jun 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : work_schedule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'type' => [
        'flextime' => 'Orario flessibile',
        'weekly' => 'Orario settimanale fisso',
        'per_weekday' => 'Per giorno della settimana',
        'trust' => 'Orario di lavoro fiduciario',
    ],
    'type_hint' => [
        'flextime' => 'Obiettivo giornaliero uniforme nei giorni lavorativi, con fasce centrali e di riferimento.',
        'weekly' => 'Un solo obiettivo settimanale, liberamente distribuibile sulla settimana.',
        'per_weekday' => 'Ore individuali oppure orari fissi di inizio–fine per ogni giorno.',
        'trust' => 'Nessun monitoraggio degli obiettivi – viene registrata solo la presenza effettiva.',
    ],
];
