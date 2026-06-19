<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : manufacturing.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Fertigung',

    'capacity' => [
        'title' => 'Kapazität',
        'subtitle' => 'Arbeitsplätze und Last (inkl. Rüstzeit) im gewählten Zeitraum',
        'day' => 'Tag',
        'period_note' => 'Auslastung über den Header-Zeitraum :from – :to (Kapazität = Tageskapazität × Tage).',
        'add' => 'Arbeitsplatz anlegen',
        'empty' => 'Keine Arbeitsplätze angelegt.',
        'work_center' => 'Arbeitsplatz',
        'code' => 'Kürzel',
        'capacity' => 'Kapazität',
        'planned' => 'Geplant',
        'free' => 'Frei',
        'utilization' => 'Auslastung',
        'setup' => 'Rüstzeit',
        'assign' => 'Arbeitsplatz zuweisen',
        'minutes' => 'Minuten',
        'flash' => [
            'created' => 'Arbeitsplatz angelegt.',
            'assigned' => 'Arbeitsplatz zugewiesen.',
            'assign_failed' => 'Zuweisung nicht möglich.',
        ],
    ],

    'planning' => [
        'title' => 'Fertigungsplanung',
        'subtitle' => 'Mehrstufiger Materialbedarf (MRP) und Qualitätskennzahlen',
        'explode' => 'Bedarf auflösen',
        'requirements' => 'Materialbedarf',
        'no_bom' => 'Keine Stückliste hinterlegt.',
        'level' => 'Stufe',
        'source' => 'Bezug',
        'make' => 'Eigenfertigung',
        'buy' => 'Zukauf',
        'gross' => 'Brutto',
        'net' => 'Netto',
        'quality' => 'Qualitätskennzahlen',
        'yield' => 'Ausbeute',
        'scrap_rate' => 'Ausschussquote',
        'rework_rate' => 'Nacharbeitsquote',
        'spc' => 'SPC (Mess-Schritte)',
        'measurement' => 'Messung',
        'out_of_spec' => 'Außerhalb Toleranz',
    ],

    'procurement_mode' => [
        'in_house' => 'Eigenfertigung',
        'purchase' => 'Zukauf',
        'subcontract' => 'Fremdfertigung',
    ],

    'quantity_kind' => [
        'fixed' => 'Feste Menge',
        'per_unit' => 'Menge pro Stück',
        'ratio' => 'Anteil (Rezeptur)',
    ],

    'status' => [
        'draft' => 'Entwurf',
        'released' => 'Freigegeben',
        'in_progress' => 'In Arbeit',
        'waiting' => 'Wartet',
        'blocked' => 'Blockiert',
        'completed' => 'Abgeschlossen',
        'cancelled' => 'Abgebrochen',
    ],

    'facturation_status' => [
        'pending' => 'Offen',
        'handed_over' => 'Übergeben',
        'invoiced' => 'Fakturiert',
        'failed' => 'Fehlgeschlagen',
        'not_required' => 'Nicht erforderlich',
    ],

    'bom_override' => [
        'disable' => 'Deaktivieren',
        'override_qty' => 'Menge überschreiben',
        'add' => 'Hinzufügen',
    ],

    'substitute_status' => [
        'requested' => 'Beantragt',
        'approved' => 'Genehmigt',
        'rejected' => 'Abgelehnt',
    ],

    'procurement_status' => [
        'open' => 'Offen',
        'ordered' => 'Bestellt',
        'closed' => 'Geschlossen',
    ],

    'order' => [
        'title' => 'Fertigungsaufträge',
        'subtitle' => 'Fertigungs-/Montageaufträge planen, freigeben und rückmelden.',
        'empty' => 'Noch keine Fertigungsaufträge.',
        'action' => [
            'create' => 'Auftrag anlegen',
            'release' => 'Freigeben',
            'start' => 'Starten',
            'reserve' => 'Material reservieren',
            'report' => 'Rückmeldung',
            'deliver' => 'Ausliefern',
            'subcontract' => 'Fremdfertigung vergeben',
            'cancel' => 'Abbrechen',
        ],
        'field' => [
            'target_qty' => 'Sollmenge',
            'good' => 'Gutmenge',
            'scrap' => 'Ausschuss',
            'rework' => 'Nacharbeit',
            'produced' => 'Produziert',
            'quantity' => 'Menge',
            'materials' => 'Material',
            'reports' => 'Rückmeldungen',
        ],
        'flash' => [
            'created' => 'Auftrag angelegt.',
            'released' => 'Auftrag freigegeben.',
            'started' => 'Auftrag gestartet.',
            'reserved' => 'Material reserviert.',
            'reported' => 'Rückmeldung erfasst.',
            'delivered' => 'Ausgeliefert.',
            'subcontracted' => 'An Lieferanten vergeben (Bestellung angelegt).',
            'subcontract_failed' => 'Fremdvergabe nicht möglich.',
            'cancelled' => 'Auftrag abgebrochen.',
            'deliver_needs_variant_warehouse' => 'Auslieferung benötigt Variante und Lagerort.',
        ],
    ],
];
