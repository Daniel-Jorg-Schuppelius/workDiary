<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : claims.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'pattern' => [
        'title' => 'Auffällige Muster (Serienfehler, Chargen)',
        'hint' => 'Gruppen mit mindestens :threshold Reklamationen im Zeitraum. Hinweis, keine Entscheidung.',
        'none' => 'Keine auffälligen Muster im Zeitraum.',
        'rule_label' => 'Regel',
        'group' => 'Gruppe',
        'cases' => 'Fälle',
        'count' => 'Anzahl',
        'rule' => [
            'lot' => 'Charge',
            'article_defect' => 'Artikel × Mangelart',
            'article_cause' => 'Artikel × Ursache',
            'supplier_defect' => 'Lieferant × Mangelart',
            'entry_type_cause' => 'Auftragsart × Ursache',
        ],
        'notify_title' => 'Auffälliges Reklamationsmuster: :label',
        'notify_message' => ':count Reklamationen in :days Tagen (:rule).',
    ],
    // Retourenlabel einer RMA (MVP-917).
    'return_label' => [
        'title' => 'Retourenlabel',
        'create' => 'Retourenlabel erstellen',
        'download' => 'Label herunterladen',
        'created' => 'Retourenlabel erstellt (Sendung :tracking).',
        'no_address' => 'Für das Retourenlabel fehlt die Anschrift des Kunden (Straße, PLZ, Ort).',
    ],
    // Retourenanmeldung im Kundenportal (MVP-935).
    'portal_return' => [
        'capability' => 'Rücksendung anmelden',
        'nav' => 'Rücksendung anmelden',
        'title' => 'Rücksendung anmelden',
        'intro' => 'Wählen Sie die Lieferung oder das Objekt, beschreiben Sie den Grund und fügen Sie bei Bedarf Fotos bei. Sie erhalten eine Rücksendenummer; ein Retourenlabel stellen wir bei Bedarf bereit.',
        'empty' => 'Für Ihr Konto liegen keine Lieferungen oder Objekte vor.',
        'submit' => 'Rücksendung anmelden',
        'label' => 'Retourenlabel herunterladen',
        'field' => [
            'delivery' => 'Lieferung',
            'asset' => 'Objekt',
            'serial_no' => 'Seriennummer',
            'quantity' => 'Menge',
            'title' => 'Kurzbeschreibung',
            'description' => 'Grund der Rücksendung',
            'photos' => 'Fotos oder Belege (höchstens 5)',
        ],
        'flash' => [
            'submitted' => 'Rücksendung angemeldet: Reklamation :number, Rücksendenummer :rma.',
        ],
        'error' => [
            'subject' => 'Bitte wählen Sie eine Lieferung oder ein Objekt.',
            'serial' => 'Diese Seriennummer gehört nicht zur gewählten Lieferung.',
        ],
    ],
];
