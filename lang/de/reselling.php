<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : reselling.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Lizenz-Reselling-Abgleich (Feature 151, MVP-757).
return [
    'title' => [
        'menu' => 'Lizenz-Abgleich',
        'index' => 'Lizenz-Reselling-Abgleich',
        'show' => 'Abgleichslauf',
    ],
    'subtitle' => 'Marketplace-Abos (Telekom, Quality Hosting) gegen die Lexoffice-Ausgangsrechnungen legen: fehlende, teilweise und unter Einkauf berechnete Perioden, dazu die Preisprüfung gegen die Reseller-Preisliste.',
    'action' => [
        'new' => 'Neuer Lauf',
        'download' => 'CSV',
        'delete' => 'Löschen',
        'refresh' => 'Aktualisieren',
        'assign' => 'Zuordnen',
        'rerun' => 'Neu berechnen',
        'remove_mapping' => 'Zuordnung entfernen',
        'back' => 'Zur Übersicht',
    ],
    'dialog' => [
        'title' => 'Neuen Abgleichslauf starten',
        'hint' => 'Mindestens eine Exportdatei ist nötig. Der Lauf liest Lexoffice im Hintergrund; das dauert bei vielen Kunden einige Minuten.',
        'telekom' => 'Telekom Cloud Marketplace: purchases.csv',
        'qualityhosting' => 'Quality Hosting: Vertragsexport (.xlsx)',
        'pricelist' => 'Quality Hosting: Preisliste (.xlsx, optional)',
        'map' => 'Zuordnungsdatei (optional)',
        'map_hint' => 'Eine Zeile je Firma: „Firma;Lexoffice-Kontakt-UUID“ oder „Firma;customer:<Sqid>“. Für alles, was der Lauf nicht eindeutig zuordnet.',
        'reference' => 'Stichtag',
        'reference_hint' => 'Perioden, die bis zu diesem Tag begonnen haben, gelten als fällig.',
        'before' => 'Tage vor Periodenbeginn',
        'after' => 'Tage nach Periodenbeginn',
        'window_hint' => 'Eine Rechnung zählt zur Periode, wenn ihr Datum in diesem Fenster um den Periodenbeginn liegt.',
        'submit' => 'Lauf starten',
    ],
    'field' => [
        'created' => 'Gestartet', 'status' => 'Status', 'sources' => 'Quellen', 'reference' => 'Stichtag',
        'periods' => 'Perioden', 'problems' => 'Auffällig', 'open_fee' => 'Offene Einkaufsgebühr', 'unmapped' => 'Ohne Zuordnung',
        'window' => 'Fenster', 'files' => 'Dateien', 'by' => 'Von', 'error' => 'Fehler', 'price_flags' => 'Preishinweise',
        'company' => 'Firma', 'customer' => 'Kunde', 'contact' => 'Lexoffice-Kontakt', 'mapping' => 'Zuordnung', 'candidates' => 'Kandidaten',
        'source' => 'Quelle', 'edition' => 'Edition', 'period' => 'Periode', 'quantity' => 'Menge', 'purchase' => 'Einkauf',
        'vouchers' => 'Rechnung(en)', 'unit_net' => 'Netto/Stück', 'note' => 'Hinweis', 'succession' => 'Ablösung',
        'voucher' => 'Rechnung', 'date' => 'Datum', 'position' => 'Position', 'remaining' => 'Restmenge',
        'product' => 'Produkt', 'term' => 'Laufzeit', 'running' => 'Stück laufend', 'contract_price' => 'Einkauf Vertrag', 'list_price' => 'Einkauf Liste',
        'uvp' => 'UVP', 'sales' => 'Verkauf (Median, Anzahl)', 'sales_range' => 'Verkauf min – max', 'margin' => 'Marge zur Liste',
        'telekom_from' => 'Telekom ab', 'telekom_to' => 'Telekom bis', 'successor' => 'QH-Vertrag', 'successor_from' => 'QH ab',
        'billed_via' => 'Abrechnung über Partner (Fremdkunde)',
        'stored_mapping' => 'Gespeicherte Zuordnung',
        'valid_from' => 'Preisliste gültig ab',
    ],
    'status' => [
        'queued' => 'Wartet',
        'running' => 'Läuft',
        'done' => 'Fertig',
        'failed' => 'Fehlgeschlagen',
    ],
    'section' => [
        'summary' => 'Zusammenfassung', 'price' => 'Preisprüfung', 'findings' => 'Perioden', 'mappings' => 'Zuordnung Marketplace-Firma → Lexoffice-Kontakt',
        'extras' => 'Microsoft-Positionen ohne fällige Periode', 'successions' => 'Ablösungen Telekom → Quality Hosting', 'issues' => 'Hinweise aus den Dateien', 'errors' => 'Fehler beim Lesen', 'files' => 'Dateien und Optionen',
    ],
    'filter' => [
        'status' => 'Status', 'problems' => 'Nur auffällige', 'all' => 'Alle', 'company' => 'Firma', 'all_companies' => 'Alle Firmen',
    ],
    'empty' => [
        'runs' => 'Noch kein Lauf. Lade die Exportdateien hoch, um den ersten Abgleich zu starten.', 'findings' => 'Keine Perioden in dieser Auswahl.', 'price' => 'Keine laufenden Verträge oder keine Preisliste hochgeladen.', 'mappings' => 'Keine Firmen.', 'extras' => 'Keine Zusatzpositionen.', 'successions' => 'Keine Ablösungen erkannt.',
    ],
    'price_flag' => [
        'below_list' => 'unter Einkauf', 'below_uvp' => 'unter UVP', 'contract_above_list' => 'Vertrag teurer als Liste', 'no_sales' => 'keine Rechnungsdaten', 'no_list' => 'nicht in Preisliste',
    ],
    'flash' => [
        'mapping_saved' => 'Zuordnung gespeichert. Mit „Neu berechnen“ wirkt sie im Bericht.', 'mapping_removed' => 'Zuordnung entfernt.', 'rerun' => 'Lauf wird neu berechnet.',
        'created' => 'Lauf gestartet. Der Bericht erscheint hier, sobald Lexoffice gelesen ist.', 'deleted' => 'Lauf gelöscht.', 'not_done' => 'Der Lauf ist noch nicht fertig.',
    ],
    'validation' => [
        'customer_required' => 'Bitte einen Kunden wählen.', 'contact_required' => 'Bitte eine Lexoffice-Kontakt-UUID angeben.',
        'need_file' => 'Mindestens eine Exportdatei (Telekom oder Quality Hosting) ist nötig.',
    ],
    'hint' => [
        'run_pending' => 'Der Lauf ist noch nicht fertig. Die Seite zeigt den Bericht nach dem Aktualisieren.', 'run_failed' => 'Der Lauf ist fehlgeschlagen.', 'unmapped' => 'Firmen ohne Zuordnung kannst du über eine Zuordnungsdatei beim nächsten Lauf auflösen.', 'extras' => 'Berechnet ohne laufendes Abo oder eine Edition, die der Abgleich nicht erkennt.',
        'mapping' => 'Über „Zuordnen“ legst du je Firma fest, wer die Rechnung bekommt: die Firma selbst, ein Partner oder ein Lexoffice-Kontakt. Gespeicherte Zuordnungen gehen der automatischen Erkennung vor.',
        'foreign' => 'Endkunden eines Partners (Fremdkunden) werden über den Partner geprüft: Die Rechnung geht an den Partner, der sie weiterreicht. Fremdkunden legst du am Partner-Kunden an, oder du trägst in der Zuordnungsdatei „Firma;partner:<Name oder Sqid>“ ein.',
        'succession' => 'Die Telekom-Laufzeit wurde am Vertragsstart bei Quality Hosting gekappt, sonst zählte jede Migration doppelt.', 'price' => 'Verkaufspreise stammen aus den zugeordneten Rechnungspositionen; Einkauf Liste und UVP aus der Preisliste für dieselbe Laufzeit und denselben Rhythmus.',
    ],
    'source' => [
        'telekom' => 'Telekom', 'qualityhosting' => 'Quality Hosting',
    ],
    'mapping' => [
        'title' => 'Firma zuordnen',
        'submit' => 'Zuordnung speichern',
        'hint' => 'Die Zuordnung gilt für alle künftigen Läufe dieser Organisation. Danach „Neu berechnen“, damit sie im Bericht wirkt.',
        'mode_label' => 'Abrechnung',
        'mode' => [
            'customer' => 'Direkt: die Firma ist der Kunde',
            'partner' => 'Über einen Partner (Fremdkunde)',
            'contact' => 'Lexoffice-Kontakt',
        ],
        'mode_hint' => [
            'customer' => 'Die Rechnung geht an diesen Kunden selbst.',
            'partner' => 'Der gewählte Kunde bekommt die Rechnung und reicht sie weiter. Die Firma wird als Fremdkunde bei ihm angelegt, falls sie dort noch fehlt.',
            'contact' => 'Ohne Kundenstamm: die Rechnungen dieses Lexoffice-Kontakts werden geprüft.',
        ],
        'customer' => 'Kunde bzw. Partner',
        'customer_placeholder' => 'Kunde wählen',
        'contact' => 'Lexoffice-Kontakt-UUID',
        'contact_hint' => 'Nur bei Abrechnung „Lexoffice-Kontakt“ nötig; steht in der Lexoffice-URL des Kontakts.',
    ],
    'months' => 'Mon.',
];
