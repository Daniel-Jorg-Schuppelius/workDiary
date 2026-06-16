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
        'tax_rate' => 'Steuersatz',
        'cost_position' => 'Kostenposition',
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

    // DATEV-Buchungsstapel (Feature 045, Priorität 2 / Phase 3).
    'datev' => [
        'title' => 'DATEV-Buchungsstapel',
        'menu' => 'DATEV-Buchungsstapel',
        'subtitle' => 'Gestellte Rechnungen, Gutschriften und freigegebene Spesen eines abgeschlossenen Zeitraums als prüfbaren DATEV-Buchungsstapel (V700) übergeben.',
        'empty' => 'Noch keine Buchungsstapel angelegt.',
        'empty_sources' => 'Keine Buchungssätze in diesem Stapel.',

        'field' => [
            'batch_no' => 'Stapel-Nr.',
            'period' => 'Zeitraum',
            'status' => 'Status',
            'booking_count' => 'Buchungssätze',
            'total' => 'Summe',
            'hash' => 'Datei-Hash (SHA-256)',
            'open_ready' => 'Buchungsreife offene Belege',
            'document_ref' => 'Belegfeld 1',
            'soll_haben' => 'S/H',
            'account' => 'Konto',
            'contra_account' => 'Gegenkonto',
            'tax_key' => 'BU-Schlüssel',
            'amount' => 'Betrag (brutto)',
            'lock_flag' => 'Festschreibung',
            'include_expenses' => 'Freigegebene Spesen einbeziehen',
            'debtor_no' => 'Debitorennummer (DATEV)',
            'debtor_no_hint' => 'Leer lassen, um die Nummer automatisch aus dem konfigurierten Nummernkreis abzuleiten.',
        ],

        'lock' => [
            'on' => 'festgeschrieben',
            'off' => 'nicht festgeschrieben',
        ],

        'action' => [
            'create' => 'Stapel anlegen',
            'finalize' => 'Finalisieren',
            'download' => 'CSV herunterladen',
            'configure' => 'Konfiguration',
            'save_config' => 'Konfiguration speichern',
        ],

        'dialog' => [
            'create_title' => 'DATEV-Buchungsstapel anlegen',
            'create_hint' => 'Buchungsreife Belege des Zeitraums werden zusammengestellt. Extern geführte Rechnungen werden ausgeschlossen.',
        ],

        'hint' => [
            'period_sources' => 'Es werden gestellte/bezahlte Rechnungen mit Belegdatum im Zeitraum berücksichtigt, die noch in keinem finalisierten Stapel hängen.',
            'include_expenses' => 'Optional: zusätzlich freigegebene Spesen als Aufwandsbuchung übernehmen (MVP — vereinfachte Konten).',
        ],

        'flash' => [
            'created' => 'Buchungsstapel als Entwurf angelegt.',
            'finalized' => 'Buchungsstapel finalisiert — CSV erzeugt und Quellen als übergeben markiert.',
            'config_saved' => 'Buchhaltungs-Konfiguration gespeichert.',
        ],

        'error' => [
            'no_sources' => 'Keine buchungsreifen Belege im gewählten Zeitraum gefunden.',
            'already_finalized' => 'Der Buchungsstapel ist bereits finalisiert und unveränderlich.',
            'storage_failed' => 'Die DATEV-Datei konnte nicht gespeichert werden.',
            'unavailable' => 'Der DATEV-Export ist ein optionales, kostenpflichtiges Zusatzmodul und in dieser Installation nicht aktiviert. Eine Freischaltung ist auf Anfrage unter :contact möglich.',
            'preflight_failed' => 'Der Stapel kann wegen Preflight-Fehlern nicht finalisiert werden.',
            'no_organization' => 'Es konnte keine Organisation aufgelöst werden.',
            'roundtrip_failed' => 'Die erzeugte DATEV-Datei hat die Wiedereinlese-Prüfung nicht bestanden: :errors',
        ],

        'preflight' => [
            'no_sources' => 'Der Stapel enthält keine Buchungssätze.',
            'missing_client_numbers' => 'Berater- und Mandantennummer müssen in der Konfiguration hinterlegt sein.',
            'missing_debtor' => 'Beleg :ref hat kein gültiges Debitorenkonto.',
            'missing_revenue' => 'Beleg :ref hat kein Erlöskonto.',
            'unknown_tax_key' => 'Beleg :ref: kein BU-Schlüssel für Steuersatz :rate % hinterlegt.',
            'external_excluded' => ':count extern geführte Rechnung(en) wurden aus dem lokalen Buchungsstapel ausgeschlossen.',
        ],

        // Write→Read-Validierung (erzeugte CSV wird mit dem Toolkit erneut eingelesen).
        'roundtrip' => [
            'unsupported' => 'Die Datei wurde nicht als unterstütztes DATEV-Format erkannt.',
            'version_mismatch' => 'Unerwartete DATEV-Formatversion (:version statt 700).',
            'parse_failed' => 'Die erzeugte Datei konnte nicht erneut eingelesen werden: :message',
            'row_count_mismatch' => 'Wiedereingelesene Buchungszeilen (:actual) weichen von der Erwartung (:expected) ab.',
        ],

        // Formatkennung/-version (UI-Anzeige „Formatversion sichtbar").
        'format' => [
            'label' => 'Format',
            'value' => 'DATEV-Buchungsstapel (EXTF V700)',
            'encoding' => 'Zeichensatz',
            'verified' => 'Wiedereinlese-Prüfung bestanden',
        ],

        // Konvertierungs-Hinweise: abgeleitete/nicht direkt abbildbare Felder.
        'loss' => [
            'title' => 'Abgeleitete und vereinfachte Felder',
            'hint' => 'Diese Felder werden beim DATEV-Export abgeleitet oder vereinfacht abgebildet und sind vor der Übergabe zu prüfen.',
            'booking_date' => 'Belegdatum = Periodenanfang (aus dem Stapelzeitraum abgeleitet, nicht je Beleg).',
            'expense_account' => 'Spesen werden vereinfacht auf das Erlös-/Aufwandskonto gebucht (keine differenzierten Aufwands-/Vorsteuerkonten je Kategorie).',
            'missing_tax_key' => 'Belege ohne BU-Schlüssel werden ohne Steueraufteilung übergeben.',
        ],

        'config' => [
            'title' => 'DATEV-Buchhaltungskonfiguration',
            'subtitle' => 'Berater-/Mandantennummer, Kontenrahmen, Sachkonten, Debitoren-Nummernkreis und Steuerschlüssel je Organisation.',
            'client_group' => 'Berater & Mandant',
            'advisor_number' => 'Beraternummer',
            'client_number' => 'Mandantennummer',
            'accounts_group' => 'Konten',
            'skr' => 'Kontenrahmen',
            'account_length' => 'Sachkontenlänge',
            'revenue_account' => 'Erlöskonto (Standard)',
            'revenue_account_tax_free' => 'Erlöskonto (steuerfrei / 0 %)',
            'debtor_base' => 'Debitoren-Nummernkreis-Basis',
            'debtor_base_hint' => 'Fehlt eine explizite Debitorennummer am Kunden, wird sie aus Basis + Kunden-ID gebildet.',
            'tax_group' => 'Steuerschlüssel (DATEV-BU)',
            'tax_key_19' => 'BU-Schlüssel 19 %',
            'tax_key_7' => 'BU-Schlüssel 7 %',
            'tax_key_0' => 'BU-Schlüssel 0 % / steuerfrei',
            'export_group' => 'Export',
            'finalize' => 'Festschreibekennzeichen setzen (GoBD)',
            'finalize_hint' => 'Markiert die Buchungen beim Export als festgeschrieben.',
            'encoding' => 'Zeichensatz',
            'encoding_hint' => 'DATEV-üblich ist ISO-8859-1; UTF-8 nur, wenn ausdrücklich gewünscht.',
        ],
    ],
];
