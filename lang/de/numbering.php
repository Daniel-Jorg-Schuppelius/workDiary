<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : numbering.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Vergabe von Kunden- und Lieferantennummern durch ein Buchhaltungsprogramm (Phase 141, MVP-1130–1134).
return [
    'source' => [
        'local' => 'workDiary',
        'inactive' => ':program (nicht aktiv – vergibt workDiary)',
        'column' => 'Vergibt',
        'page_hint' => 'Vergibt ein Buchhaltungsprogramm die Kunden- oder Lieferantennummern, bekommt ein neuer Datensatz seine Nummer beim Übertragen. Bestehende Nummern bleiben unverändert. Rechnungsnummern stellen Sie in den Einstellungen des Programms ein.',
    ],
    'field' => [
        'locked_hint' => 'Vergibt :program beim Übertragen.',
    ],
    'flash' => [
        'issued' => 'Nummer :number von :program übernommen.',
        'pending' => 'Die Nummer von :program folgt beim nächsten Übertragen: :reason',
        'conflict' => 'Die Nummer :number von :program ist schon an :holder vergeben. Den Fall finden Sie in der Zuordnungs-Inbox.',
    ],
    'conflict' => [
        'title' => 'Nummer :number aus :program ist schon an :holder vergeben',
    ],
    'error' => [
        'taken_remote' => 'Die Nummer :number ist in :program schon an „:name“ vergeben.',
        'taken_local' => 'Die Nummer :number ist schon an :holder vergeben. Bitte ändern Sie dort zuerst die Nummer.',
        'no_number' => ':program hat keine Nummer zurückgegeben.',
    ],
];
