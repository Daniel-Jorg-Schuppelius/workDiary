<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : asset.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'lifecycle' => [
        'in_operation' => 'In Betrieb',
        'retired' => 'Ersetzt',
        'decommissioned' => 'Stillgelegt',
    ],
    'dossier' => [
        'title' => 'Objektakte',
        'back' => 'Zurück zum Asset',
        'generated_at' => 'Erstellt am',
        'lifecycle' => 'Lebenszyklus',
        'master_data' => 'Stammdaten',
        'health' => 'Zustand',
        'commissioned' => 'Inbetriebnahme',
        'decommissioned' => 'Außerbetriebnahme',
        'warranty' => 'Garantie bis',
        'warranty_expired' => 'abgelaufen',
        'in_service_days' => 'Einsatzdauer (Tage)',
        'room_requirements' => 'Raumbezogene Anforderungen',
        'maintenance' => 'Wartungen',
        'next_due' => 'Nächste Fälligkeit',
        'last_run' => 'Zuletzt durchgeführt',
        'due' => 'Fällig',
        'scheduled' => 'Geplant',
        'assignments' => 'Ausgaben / Rückgaben',
        'checked_out' => 'Ausgegeben',
        'assignee' => 'Empfänger',
        'returned' => 'Zurückgegeben',
        'open' => 'Offen',
        'defects' => 'Defekte / Sperren',
        'blocks' => 'Sperrt',
        'orders' => 'Aufträge',
        'timeline' => 'Lebenszyklus-Verlauf',
        'event' => [
            'asset.audit' => 'Asset-Ereignis',
            'order.linked' => 'Auftrag verknüpft',
            'protocol.linked' => 'Protokoll verknüpft',
            'material.linked' => 'Materialeinsatz verknüpft',
            'attachment.linked' => 'Anhang hinzugefügt',
            'assignment.checkedOut' => 'Ausgegeben',
            'assignment.returned' => 'Zurückgegeben',
            'defect.reported' => 'Defekt gemeldet',
            'defect.resolved' => 'Defekt behoben',
            'maintenance.completed' => 'Wartung durchgeführt',
            'unknown' => 'Ereignis',
        ],
    ],
    // Anlagen-Stückliste (Feature 118, MVP-607).
    'components' => [
        'title' => 'Stückliste',
        'empty' => 'Noch kein Teil erfasst.',
        'saved' => 'Teil erfasst.',
        'replaced' => 'Teil ersetzt — das alte bleibt in der Historie.',
        'removed' => 'Teil ausgebaut.',
        'not_installed' => 'Dieses Teil ist nicht mehr verbaut.',
        'foreign_organization' => 'Materialverbrauch und Gerät gehören zu verschiedenen Organisationen.',
        'replace_hint' => '„:name" wird ausgebaut und bleibt mit Ausbaudatum in der Historie stehen.',
        'label_hint' => 'Für Fremdteile ohne eigenen Artikelstamm.',
        'interval_hint' => 'Wechselintervall in Monaten — daraus errechnet sich die Fälligkeit.',
        'action' => [
            'add' => 'Teil hinzufügen',
            'replace' => 'Ersetzen',
            'remove' => 'Ausbauen',
        ],
        'due' => ['heading' => 'Fällige Verschleißteile', 'hint' => 'Vorschlag für den nächsten Einsatz — was wirklich getauscht wird, entscheidet der Monteur.'],
        'history' => ['heading' => 'Historie (ausgebaute und ersetzte Teile)'],
        'column' => [
            'name' => 'Teil',
            'article' => 'Artikel',
            'label' => 'Bezeichnung (Freitext)',
            'position' => 'Einbauort',
            'quantity' => 'Menge',
            'unit' => 'Einheit',
            'serial_no' => 'Seriennummer',
            'installed_on' => 'Eingebaut am',
            'removed_on' => 'Ausgebaut am',
            'due_on' => 'Wechsel fällig',
            'interval' => 'Intervall (Monate)',
            'status' => 'Status',
            'note' => 'Notiz',
        ],
        'status' => [
            'installed' => 'Verbaut',
            'removed' => 'Ausgebaut',
            'replaced' => 'Ersetzt',
        ],
    ],
];
