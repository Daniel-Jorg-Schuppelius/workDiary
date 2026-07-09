<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : prerequisites.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'blocked' => [
        'missing_required' => 'Voraussetzung fehlt',
        'missing_optional' => 'Hinweis',
        'not_licensed' => 'Nicht lizenziert',
        'not_allowed' => 'Keine Berechtigung',
        'provider_unsupported' => 'Vom Anbieter nicht unterstützt',
    ],
    'contact_role' => 'Bitte wenden Sie sich an: :role',
    'warehouses' => [
        'missing' => 'Zählung und Buchung brauchen mindestens einen Lagerort.',
        'cta' => 'Lagerorte verwalten',
    ],
    'dispatch' => [
        'cta' => 'Zum Dispositions-Panel des Auftrags',
    ],
    'mappings' => [
        'hint' => 'Zuordnungen entstehen automatisch beim Import bzw. beim Auflösen von Inbox-Einträgen (Plugin-Sync und CSV-Import).',
        'cta' => 'Zur Integrations-Inbox',
    ],
    'shift_types' => [
        'missing' => 'Es sind noch keine Schichttypen angelegt — Schichten können ohne Typ nur eingeschränkt geplant werden.',
        'cta' => 'Schichttypen anlegen',
        'dialog_hint' => 'Noch keine Schichttypen vorhanden. Die Schicht wird ohne Typ gespeichert; Schichttypen verwaltet die Administration über „Schichttypen" im Schichtplan.',
    ],
];
