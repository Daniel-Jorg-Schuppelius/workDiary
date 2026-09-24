<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : fields.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Feldschema-Baustein (MVP-866): Validierung der Definitionen, Anzeigewerte.
return [
    'validation' => [
        'invalid_row' => 'Felddefinition in Zeile :row ist ungültig.',
        'label_required' => 'Feld :row braucht eine Bezeichnung (max. 160 Zeichen).',
        'unknown_type' => 'Feld :row hat einen unbekannten Feldtyp.',
        'invalid_key' => 'Feld-Schlüssel „:key" ist ungültig (Kleinbuchstaben, Ziffern, Unterstriche).',
        'duplicate_key' => 'Feld-Schlüssel „:key" ist doppelt vergeben.',
        'select_needs_options' => 'Auswahlfeld „:label" braucht mindestens eine Option.',
        'fields_required' => 'Es wird mindestens ein Feld benötigt.',
        'too_many_fields' => 'Maximal :max Felder.',
        'range_invalid' => 'Feld „:label": Minimum darf nicht über dem Maximum liegen.',
        'condition_unknown_field' => 'Bedingung von Feld „:label" verweist auf ein unbekanntes Feld „:field".',
        'condition_cycle' => 'Bedingungen bilden einen Zyklus (Feld „:field" hängt indirekt von sich selbst ab).',
    ],
    'value' => [
        'yes' => 'Ja',
        'no' => 'Nein',
        'signed' => 'Unterschrieben',
        'attachments' => '{1} :count Anhang|[2,*] :count Anhänge',
    ],
    'action' => [
        'clear_signature' => 'Unterschrift löschen',
    ],
    'custom' => [
        'listed' => 'In der Liste zeigen',
        'title' => 'Eigene Felder',
        'legend' => 'Weitere Felder',
        'subject' => 'Träger',
        'fields' => 'Felder',
        'status' => 'Status',
        'active' => 'Aktiv',
        'inactive' => 'Deaktiviert',
        'none' => 'Noch keine Felder definiert.',
        'edit' => 'Felder bearbeiten',
        'save' => 'Speichern',
        'activate' => 'Aktivieren',
        'deactivate' => 'Deaktivieren',
        'values_count' => ':count Datensätze mit Werten',
        'version' => 'Schema-Version :version',
        'saved' => 'Eigene Felder gespeichert.',
        'toggled' => 'Status geändert.',
        'intro' => 'Je Träger eine Feldliste; Felder erscheinen im Formular, auf der Detailseite und im Export. Deaktivieren statt löschen, sobald Werte erfasst sind.',
        'validation' => [
            'too_many_listed' => 'Höchstens :max Felder können in der Liste erscheinen.',
            'unknown_subject' => 'Für diesen Träger gibt es keine eigenen Felder.',
            'type_not_allowed' => 'Feld „:label": Datei-, Foto- und Unterschriftsfelder sind hier nicht möglich.',
        ],
    ],
];
