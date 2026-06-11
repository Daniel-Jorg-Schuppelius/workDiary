<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : finance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'module' => 'Finanzschnittstelle',
        'transfers' => 'Übergabenachweise',
        'transfer' => 'Übergabenachweis',
        'menu' => 'Faktura-Übergabe',
        'positions' => 'Entstehende Positionen',
        'sources' => 'Einzelquellen (Snapshot)',
        'events' => 'Ereignisprotokoll',
    ],

    'subtitle' => [
        'transfers' => 'Abrechenbare Zeiten und Materialien in getrennten Kanälen an das führende Fakturierungssystem übergeben.',
    ],

    'field' => [
        'billing_mode' => 'Fakturierungsweg',
        'billing_mode_inherit' => '— Organisations-Standard erben —',
        'billing_mode_default' => '— WorkDiary (Standard) —',
        'billing_mode_hint' => 'Übersteuert den Organisations-Standard für diesen Kunden. Bei Lexoffice/DATEV ist die lokale Rechnungserstellung gesperrt.',
        'billing_mode_org_hint' => 'Standard-Fakturierungsweg der Organisation. Kunden können ihn einzeln übersteuern.',
        'channel' => 'Übergabekanal',
        'target' => 'Übergabeziel',
        'status' => 'Status',
        'period' => 'Leistungszeitraum',
        'position_count' => 'Positionen',
        'total_amount' => 'Gesamtbetrag (netto)',
        'total_quantity' => 'Gesamtmenge',
        'payload_hash' => 'Payload-Hash',
        'transferred_at' => 'Übergeben am',
        'failure_reason' => 'Fehlergrund',
        'customer' => 'Kunde',
        'source' => 'Quelle',
        'source_deleted' => 'Quelle nicht mehr vorhanden',
    ],

    'action' => [
        'create_draft' => 'Übergabe vorbereiten',
        'confirm' => 'Übergabe bestätigen',
        'mark_transferred' => 'Als übergeben markieren',
        'mark_failed' => 'Als fehlgeschlagen markieren',
        'void' => 'Übergabe verwerfen',
        'show' => 'Anzeigen',
        'execute' => 'Übertragen',
        'retry' => 'Erneut versuchen',
        'download' => 'Übergabepaket herunterladen',
        'open_external' => 'Extern öffnen',
    ],

    'filter' => [
        'all' => 'Alle',
    ],

    'hint' => [
        'channels_separate' => 'Zeit und Material werden als getrennte Übergabepakete bestätigt.',
        'datev_desktop_api' => 'DATEV führt: Übergabe als Datei-Paket (CSV) — die DATEV-Desktop-API folgt als eigener Adapter.',
        'target_by_mode' => 'Das Ziel wird aus dem Fakturierungsweg des Kunden vorbelegt.',
        'period_sources' => 'Gesammelt werden nur abrechenbare, noch nicht fakturierte/übergebene Quellen im Zeitraum.',
        'lexoffice_draft_created' => 'Rechnungsentwurf in Lexoffice angelegt:',
    ],

    'confirm_execute' => 'Übergabe jetzt an das Ziel übertragen? Bei Erfolg werden die Quellen als übergeben markiert.',
    'confirm_void' => 'Übergabe verwerfen? Die Quellen werden wieder freigegeben.',

    'empty_title' => 'Keine Übergabenachweise',
    'empty_message' => 'Es wurden noch keine Übergaben vorbereitet.',
    'empty_filtered' => 'Keine Übergaben für die gewählten Filter.',
    'empty_positions_title' => 'Keine Positionen',
    'empty_positions' => 'Aus den Quellen entstehen keine Positionen (z. B. Quellen gelöscht).',

    'csv' => [
        'package_title' => 'Übergabepaket WorkDiary (CSV) — kein DATEV-Format',
        'position' => 'Position',
        'date' => 'Datum',
        'employee' => 'Mitarbeiter',
        'project' => 'Projekt/Auftrag',
        'activity' => 'Tätigkeit',
        'hours' => 'Stunden',
        'rate' => 'Satz',
        'amount' => 'Betrag',
        'comment' => 'Kommentar',
        'product' => 'Produkt',
        'quantity' => 'Menge',
        'unit' => 'Einheit',
        'unit_price_net' => 'Einzelpreis netto',
        'total' => 'Summe',
    ],

    'lexoffice' => [
        'introduction' => 'Übergabe aus WorkDiary — :channel, Zeitraum :from – :to.',
    ],

    'flash' => [
        'created' => 'Übergabenachweis-Entwurf erstellt.',
        'confirmed' => 'Übergabe bestätigt.',
        'transferred' => 'Übergabe abgeschlossen — Quellen wurden als übergeben markiert.',
        'failed' => 'Übergabe als fehlgeschlagen markiert.',
        'voided' => 'Übergabe verworfen — Quellen wurden wieder freigegeben.',
    ],

    'error' => [
        'local_invoicing_locked' => 'Fakturierung führt :program; lokale Rechnungserstellung ist gesperrt.',
        'no_sources' => 'Keine übergabefähigen Quellen im gewählten Zeitraum gefunden.',
        'illegal_transition' => 'Statuswechsel von „:from" nach „:to" ist nicht erlaubt.',
        'void_after_transfer' => 'Eine bereits übergebene Übergabe kann nicht verworfen werden — bitte Storno-/Differenzübergabe verwenden.',
        'entry_already_transferred' => 'Der Zeiteintrag wurde bereits an die Fakturierung übergeben und kann nicht mehr korrigiert werden.',
        'target_not_allowed' => 'Dieses Ziel ist für den Fakturierungsweg „:mode" nicht zulässig.',
        'lexoffice_not_configured' => 'Lexoffice ist für diese Organisation nicht konfiguriert (API-Key fehlt).',
        'sources_missing' => 'Quellen des Übergabenachweises sind nicht mehr vollständig vorhanden.',
    ],
];
