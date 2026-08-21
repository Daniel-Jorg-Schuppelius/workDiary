<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : accounting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

return [
    'action' => [
        'push' => 'Transférer vers la comptabilité',
    ],

    'flash' => [
        'pushed' => 'Client transféré vers la comptabilité (ID :id).',
        'failed' => 'Le transfert a échoué : :msg',
        'no_plugin' => 'Aucun système comptable actif.',
    ],

    'error' => [
        'accounting_leads' => 'La comptabilité détient les données de base — rien n’est transféré (paramètre « autorité des données de base »).',
        'no_syncer' => 'Le plugin :plugin ne transfère pas de contacts.',
    ],

    'authority' => [
        'workdiary' => 'workDiary dirige',
        'accounting' => 'La comptabilité dirige',
    ],
];
