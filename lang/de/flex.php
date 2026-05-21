<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : flex.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'eligibility' => [
        'title' => 'Gleitzeit-Berechtigung für :name',
        'nav_title' => 'Gleitzeit-Berechtigung',
        'subtitle' => 'Zeiträume, in denen :name an der Gleitzeit-Erfassung teilnimmt.',
        'current' => [
            'active' => 'Aktuell gleitzeitberechtigt',
            'inactive' => 'Aktuell nicht gleitzeitberechtigt',
        ],
        'table' => [
            'valid_from' => 'Gültig ab',
            'valid_to' => 'Gültig bis',
            'open' => 'unbefristet',
            'note' => 'Notiz',
            'actions' => 'Aktionen',
        ],
        'form' => [
            'add_title' => 'Neue Periode anlegen',
            'valid_from' => 'Gültig ab',
            'valid_to' => 'Gültig bis (leer = unbefristet)',
            'note' => 'Notiz (optional)',
            'submit' => 'Periode anlegen',
            'end_today' => 'Heute beenden',
            'end_submit' => 'Beenden',
        ],
        'flash' => [
            'saved' => 'Gleitzeit-Periode gespeichert.',
            'deleted' => 'Gleitzeit-Periode gelöscht.',
        ],
        'empty' => 'Für :name sind keine Gleitzeit-Perioden hinterlegt — er nimmt damit nicht an der Gleitzeit teil.',
        'confirm_delete' => 'Diese Periode wirklich löschen? Saldo-Berechnungen werden neu durchgeführt.',
    ],
];
