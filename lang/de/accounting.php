<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : accounting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

return [
    'action' => [
        'push' => 'An Buchhaltung übertragen',
    ],

    'flash' => [
        'pushed' => 'Kunde an die Buchhaltung übertragen (ID :id).',
        'failed' => 'Übertragung fehlgeschlagen: :msg',
        'no_plugin' => 'Kein Buchhaltungssystem aktiv.',
    ],

    'error' => [
        'accounting_leads' => 'Die Buchhaltung führt die Stammdaten — es wird nicht übertragen (Einstellung „Stammdaten-Hoheit").',
        'no_syncer' => 'Das Plugin :plugin überträgt keine Kontakte.',
    ],

    'authority' => [
        'workdiary' => 'workDiary führt',
        'accounting' => 'Buchhaltung führt',
    ],

    // Lokale Buchhaltung (Feature 125, MVP-671): Einrichtung, Buchungshoheit,
    // Geschäftsjahre und Preflight.
    'ledger' => [
        'title' => 'Lokale Buchhaltung',
        'menu' => 'Buchhaltung',
        'setup_menu' => 'Einrichtung',
        'subtitle' => 'Buchungshoheit, Geschäftsjahre und Einrichtungs-Preflight der lokalen Buchführung.',
        'open_ended' => 'laufend',
        'section' => [
            'profile' => 'Buchhaltungsprofil',
            'preflight' => 'Preflight',
            'fiscal_years' => 'Geschäftsjahre',
            'sovereignty' => 'Buchungshoheit',
        ],
        'field' => [
            'profit_determination' => 'Gewinnermittlung',
            'base_currency' => 'Basiswährung',
            'fiscal_year_start_month' => 'Geschäftsjahr beginnt im',
            'starts_on' => 'Buchungsbeginn (Stichtag)',
            'note' => 'Notiz',
            'fiscal_year_starts_on' => 'Beginn des Geschäftsjahres',
            'fiscal_year_label' => 'Bezeichnung',
            'sovereignty' => 'Neue Buchungshoheit',
            'external_provider' => 'Führendes System',
            'valid_from' => 'Gültig ab',
            'reason' => 'Begründung',
            'datev_account' => 'DATEV-Konto',
            'euer_category' => 'EÜR-Zeile',
            'euer_category_none' => '— ohne Zuordnung —',
            'deductible_percent' => 'Abziehbarer Anteil (%)',
            'description' => 'Beschreibung',
            'post_now' => 'Sofort festschreiben',
            'reversal_reason' => 'Begründung',
            'reversal_booked_on' => 'Buchungsdatum der Gegenbuchung',
        ],
        'hint' => [
            'profit_determination' => 'Ändert die Auswertung (EÜR oder doppelte Buchführung), nicht die Buchungs- und Nachweisregeln.',
            'base_currency' => 'Der erste Schnitt führt genau eine Währung; abweichende Belege werden später mit Grund angezeigt statt umgerechnet.',
            'starts_on' => 'Ab diesem Tag entstehen lokale Buchungen. Belege davor bleiben Historie und werden nicht rückwirkend gebucht.',
            'fiscal_year_starts_on' => 'Es entstehen zwölf Monatsperioden bis zum Vortag des Folgejahres.',
            'fiscal_year_label' => 'Leer lassen für „2026" bzw. „2026/2027" bei abweichendem Geschäftsjahr.',
            'sovereignty' => 'Wer das Hauptbuch für welchen Zeitraum führte, bleibt nachvollziehbar — auch nach einem Wechsel.',
            'sovereignty_switch' => 'Der Datenumzug bleibt der Buchhaltungswechsel; hier wird nur die Führung ab Stichtag umgehängt.',
            'external_provider' => 'Nur bei externer Hoheit: Name des führenden Systems (z. B. lexoffice).',
            'datev_account' => 'Nur für den Export; die lokale Buchung hängt nicht daran.',
            'euer_category' => 'Bestimmt, in welcher Zeile der Anlage EÜR das Konto erscheint. Ohne Zuordnung landet es in den ungeklärten Fällen.',
            'deductible_percent' => 'Wirkt nur in der EÜR-Auswertung — im Journal steht immer der volle Betrag (z. B. 70 % bei Bewirtung).',
            'normal_balance' => 'Vorbelegt aus der Kontoart, im Einzelfall überschreibbar.',
            'post_now' => 'Nach dem Festschreiben ist die Buchung nur noch über eine Gegenbuchung korrigierbar.',
            'reversal_booked_on' => 'Leer lassen für den Originaltag, sofern dessen Periode noch offen ist.',
        ],
        'action' => [
            'activate' => 'Lokale Buchhaltung aktivieren',
            'add_fiscal_year' => 'Geschäftsjahr anlegen',
            'switch' => 'Buchungshoheit wechseln',
            'switch_submit' => 'Führung umhängen',
            'add_account' => 'Konto anlegen',
            'edit_account' => 'Konto bearbeiten',
            'deactivate' => 'Stilllegen',
            'add_entry' => 'Buchung erfassen',
            'post' => 'Festschreiben',
            'reverse' => 'Stornieren',
            'reverse_submit' => 'Gegenbuchung erzeugen',
        ],
        'column' => [
            'fiscal_year' => 'Geschäftsjahr',
            'range' => 'Zeitraum',
            'periods' => 'Perioden',
            'status' => 'Status',
            'from' => 'Ab',
            'to' => 'Bis',
            'holder' => 'Führung',
            'reason' => 'Begründung',
            'number' => 'Konto',
            'name' => 'Bezeichnung',
            'type' => 'Kontoart',
            'normal_balance' => 'Saldenrichtung',
            'flags' => 'Merkmale',
            'journal_no' => 'Nr.',
            'booked_on' => 'Buchungsdatum',
            'document_on' => 'Belegdatum',
            'memo' => 'Buchungstext',
            'accounts' => 'Konten',
            'amount' => 'Betrag',
            'debit' => 'Soll',
            'credit' => 'Haben',
            'account' => 'Konto',
            'document_reference' => 'Beleg',
            'posted_by' => 'Festgeschrieben von',
            'source' => 'Quelle',
        ],
        'empty' => [
            'accounts' => 'Noch kein Konto angelegt.',
            'entries' => 'Noch keine Buchung im Zeitraum.',
            'fiscal_years' => 'Noch kein Geschäftsjahr angelegt.',
            'sections' => 'Noch kein Führungswechsel erfasst.',
        ],
        'flash' => [
            'saved' => 'Buchhaltungsprofil gespeichert.',
            'activated' => 'Lokale Buchhaltung aktiviert.',
            'fiscal_year_created' => 'Geschäftsjahr :year mit Perioden angelegt.',
            'sovereignty_switched' => 'Buchungshoheit gewechselt.',
            'account_saved' => 'Konto gespeichert.',
            'account_deactivated' => 'Konto stillgelegt.',
            'imported' => 'Kontenimport: :imported neu, :updated aktualisiert, :errors Fehler.',
            'entry_saved' => 'Buchung gespeichert.',
            'entry_posted' => 'Buchung festgeschrieben.',
            'entry_reversed' => 'Gegenbuchung erzeugt.',
        ],
        'error' => [
            'sovereignty' => 'Für den :date führt :holder das Hauptbuch — lokal darf für diesen Tag nicht festgeschrieben werden.',
            'fiscal_year_overlap' => 'Der Zeitraum überschneidet sich mit dem Geschäftsjahr :year.',
            'start_locked' => 'Der Buchungsbeginn ist nach der Aktivierung nicht mehr änderbar.',
            'provider_required' => 'Bei externer Buchungshoheit muss das führende System benannt werden.',
            'sovereignty_unchanged' => 'Diese Buchungshoheit gilt für den Stichtag bereits.',
            'later_section_exists' => 'Es gibt bereits einen späteren Führungsabschnitt ab :date.',
            'period_closed' => 'Die Periode ab :period nimmt keine Buchungen mehr an.',
            'no_period' => 'Für den :date gibt es keine Buchungsperiode.',
            'entry_frozen' => 'Die Buchung ist festgeschrieben — Korrektur nur über eine Gegenbuchung.',
            'needs_two_lines' => 'Eine Buchung braucht mindestens zwei Zeilen.',
            'unknown_account' => 'Eine Zeile verweist auf ein unbekanntes Konto.',
            'inactive_account' => 'Das Konto :account ist stillgelegt.',
            'foreign_currency_line' => 'Alle Zeilen müssen auf :currency lauten.',
            'negative_amount' => 'Beträge sind positiv; die Richtung ergibt sich aus Soll oder Haben.',
            'both_sides' => 'Eine Zeile trägt entweder Soll oder Haben, nie beides.',
            'unbalanced' => 'Soll (:debit) und Haben (:credit) stimmen nicht überein.',
            'reverse_not_posted' => 'Nur eine festgeschriebene Buchung kann storniert werden.',
            'reversal_reason_required' => 'Für den Storno ist eine Begründung Pflicht.',
            'account_in_use' => 'Auf dieses Konto wurde bereits gebucht — es kann nur stillgelegt werden.',
            'entry_without_organization' => 'Die Buchung hat keine Organisation — bitte den Systembetreuer informieren.',
            'account_number_taken' => 'Diese Kontonummer ist bereits vergeben.',
        ],
        'preflight' => [
            'not_configured' => 'Profil noch nicht gespeichert — der Preflight läuft ab der ersten Speicherung.',
            'blocked_hint' => 'Die Aktivierung bleibt gesperrt, solange ein Punkt rot ist.',
            'profile_missing' => 'Es ist noch kein Buchhaltungsprofil gespeichert.',
            'starts_on_missing' => 'Es ist kein Buchungsbeginn gesetzt.',
            'starts_on_ok' => 'Buchungsbeginn: :date.',
            'fiscal_year_missing' => 'Für den Buchungsbeginn gibt es kein Geschäftsjahr.',
            'periods_missing' => 'Das Geschäftsjahr :year hat keine Perioden.',
            'fiscal_year_ok' => 'Geschäftsjahr :year mit :count Perioden.',
            'migration_active' => 'Ein Buchhaltungswechsel läuft (:status) — die Führung ist währenddessen nicht eindeutig.',
            'migration_none' => 'Kein laufender Buchhaltungswechsel.',
            'handed_over' => 'Der DATEV-Stapel :batch deckt den Zeitraum bis :to bereits ab.',
            'handed_over_none' => 'Kein exportierter Buchungsstapel überschneidet den Zeitraum.',
            'sovereignty_conflict' => 'Ab :date führt bereits :holder — der Zeitraum wäre doppelt belegt.',
            'sovereignty_ok' => 'Kein konkurrierender Führungsabschnitt.',
            'foreign_currency' => ':count Belege ab dem Stichtag lauten nicht auf :currency; sie bleiben in der Buchungs-Inbox sichtbar.',
            'base_currency_ok' => 'Alle Belege ab dem Stichtag lauten auf :currency.',
            'billing_external' => 'Die Rechnungen stellt :program — die Belege kommen dann von dort.',
            'billing_local' => 'workDiary stellt die Ausgangsrechnungen selbst.',
            'master_data_external' => 'Die Stammdaten führt die Buchhaltung; Kunden und Lieferanten werden nicht von hier überschrieben.',
            'master_data_local' => 'workDiary führt die Stammdaten.',
            'key' => [
                'profile' => 'Profil',
                'starts_on' => 'Stichtag',
                'fiscal_year' => 'Geschäftsjahr',
                'migration_run' => 'Wechsel',
                'handed_over' => 'Übergaben',
                'sovereignty' => 'Führung',
                'base_currency' => 'Währung',
                'billing_mode' => 'Faktura',
                'master_data' => 'Stammdaten',
            ],
        ],
        'reversal_memo' => 'Storno zu Buchung #:no',
        'opening_memo' => 'Eröffnungsbuchung',
        'reverse_hint' => 'Der Storno erzeugt eine echte Gegenbuchung. Die ursprüngliche Buchung bleibt inhaltlich unverändert stehen.',
        'accounts' => [
            'title' => 'Kontenplan',
            'menu' => 'Kontenplan',
            'subtitle' => 'Konten, Saldenrichtung und DATEV-Zuordnung der lokalen Buchhaltung.',
        ],
        'journal' => [
            'title' => 'Buchungsjournal',
            'menu' => 'Journal',
            'subtitle' => 'Festgeschriebene und vorbereitete Buchungen im gewählten Zeitraum.',
        ],
        'entry' => [
            'title' => 'Buchung',
            'head' => 'Buchungskopf',
            'lines' => 'Buchungszeilen',
            'total' => 'Summe',
            'is_reversal_of' => 'Diese Buchung storniert die Buchung #:no.',
            'reversed_by' => 'Storniert durch Buchung #:no — :reason',
        ],
        'filter' => [
            'only_active' => 'nur aktive',
            'all_types' => 'Alle Kontoarten',
            'all_states' => 'Alle Zustände',
        ],
        'flag' => [
            'open_item' => 'Offene Posten',
            'bank' => 'Bank',
            'cash' => 'Kasse',
            'clearing' => 'Klärung',
            'inactive' => 'Stillgelegt',
        ],
        'confirm' => [
            'deactivate' => 'Konto wirklich stilllegen? Bestehende Buchungen bleiben erhalten.',
        ],
        'import' => [
            'line_invalid' => 'Zeile :line übersprungen (Nummer, Name oder Kontoart fehlt).',
        ],
    ],

    // Buchungs-Inbox und Mappingregeln (Feature 125, MVP-673).
    'inbox' => [
        'title' => 'Buchungs-Inbox',
        'menu' => 'Buchungs-Inbox',
        'subtitle' => 'Belege, Auslagen und Kassenvorgänge des Zeitraums mit ihrem Buchungsstatus.',
        'empty' => 'Keine offenen Vorgänge im Zeitraum.',
        'four_eyes_active' => 'Vier-Augen-Prinzip aktiv: Wer einen Vorschlag vorbereitet hat, schreibt ihn nicht selbst fest.',
        'state' => [
            'blocked' => 'Blockiert',
            'open' => 'Ungebucht',
            'ready' => 'Bereit',
            'posted' => 'Gebucht',
        ],
        'column' => [
            'kind' => 'Quelle',
            'document' => 'Beleg',
            'booked_on' => 'Datum',
            'proposal' => 'Vorschlag',
        ],
        'filter' => [
            'all_kinds' => 'Alle Quellen',
            'include_posted' => 'gebuchte zeigen',
        ],
        'action' => [
            'prepare' => 'Vorschlag übernehmen',
            'prepare_and_post' => 'Übernehmen und festschreiben',
            'batch_prepare' => 'Alle übernehmen',
            'batch_post' => 'Alle übernehmen und festschreiben',
        ],
        'confirm' => [
            'batch' => 'Alle nicht blockierten Vorgänge des Zeitraums als Entwurf übernehmen?',
            'batch_post' => 'Alle nicht blockierten Vorgänge übernehmen UND festschreiben? Festbuchungen sind nur über Gegenbuchungen korrigierbar.',
        ],
        'flash' => [
            'prepared' => 'Vorschlag übernommen.',
            'batch' => 'Stapel: :prepared übernommen, :posted festgeschrieben, :failed offen.',
        ],
        'error' => [
            'four_eyes' => 'Vier-Augen-Prinzip: Diese Buchung wurde von Ihnen vorbereitet — sie muss jemand anderes festschreiben.',
        ],
        'blocker' => [
            'missing_rule' => 'Keine Buchungsregel für :role:criteria.',
            'handed_over' => 'Der Beleg ist bereits in einem exportierten Buchungsstapel enthalten.',
            'no_tax_breakdown' => 'Der Beleg hat keine Steueraufschlüsselung.',
            'no_amount' => 'Der Beleg hat keinen Betrag.',
            'no_lines' => 'Der Vorschlag hat keine Buchungszeilen.',
            'sovereignty' => 'Für diesen Zeitraum führt die Organisation kein lokales Hauptbuch.',
            'foreign_currency' => 'Der Beleg lautet auf :currency, die Buchhaltung führt :base — eine belegbare Umrechnung gibt es noch nicht.',
            'unsupported_target' => 'Für dieses Zahlungsziel gibt es noch keinen Buchungsweg.',
        ],
        'memo' => [
            'sales_invoice' => 'Rechnung :number · :customer',
            'incoming_invoice' => 'Eingangsrechnung :number · :seller',
            'expense' => 'Auslage :description · :user',
            'cash_entry' => 'Kasse :register · :purpose',
            'payment' => 'Zahlung (:kind) · :target',
        ],
        'reversal_reason' => [
            'unmatched' => 'Zahlungszuordnung aufgehoben — Gegenbuchung.',
        ],
    ],
    'rules' => [
        'title' => 'Buchungsregeln',
        'menu' => 'Buchungsregeln',
        'subtitle' => 'Zuordnung von Quelle und Rolle zu einem Konto — versioniert und stichtagsfähig.',
        'empty' => 'Noch keine Buchungsregel angelegt.',
        'fallback' => 'Auffangregel (alle Fälle)',
        'no_tax_code' => '— ohne Steuerkennzeichen —',
        'column' => [
            'role' => 'Rolle',
            'match' => 'Merkmale',
            'validity' => 'Gültigkeit',
            'priority' => 'Priorität',
        ],
        'field' => [
            'tax_code' => 'Steuerkennzeichen',
            'match_key' => 'Merkmal',
            'match_value' => 'Wert',
        ],
        'hint' => [
            'role' => 'Wofür steht das Konto in der Buchung — Erlös, Forderung, Vorsteuer …',
            'tax_code' => 'Optional; ordnet das eingefrorene Steuerergebnis des Belegs einem Konto zu.',
            'match' => 'Leer lassen für die Auffangregel. Beispiele: tax_rate = 19.00, expense_category_id = 5.',
            'priority' => 'Höher gewinnt; bei Gleichstand die spezifischere Regel.',
        ],
        'action' => [
            'add' => 'Regel anlegen',
            'edit' => 'Regel bearbeiten',
        ],
        'confirm' => [
            'deactivate' => 'Regel stilllegen? Bestehende Buchungen behalten ihre Regelversion.',
        ],
        'flash' => [
            'saved' => 'Buchungsregel gespeichert.',
            'versioned' => 'Neue Regelfassung ab dem Stichtag angelegt.',
            'deactivated' => 'Buchungsregel stillgelegt.',
        ],
    ],

    // Offene Posten (Feature 125, MVP-674).
    'open_items' => [
        'title' => 'Offene Posten',
        'menu' => 'Offene Posten',
        'subtitle' => 'Forderungen und Verbindlichkeiten aus den Festbuchungen, mit Altersstruktur.',
        'empty' => 'Keine offenen Posten.',
        'overdue_days' => ':days Tage überfällig',
        'settle_hint' => 'Offen: :open. Zahlungen kommen aus dem Zahlungsabgleich — hier nur Skonto, Einbehalt oder Ausbuchung.',
        'column' => [
            'counterparty' => 'Gegenpartei',
            'due_date' => 'Fällig',
            'original' => 'Ursprung',
            'open' => 'Offen',
            'kind' => 'Art',
        ],
        'bucket' => [
            'not_due' => 'Nicht fällig',
            'd30' => '1–30 Tage',
            'd60' => '31–60 Tage',
            'd90' => '61–90 Tage',
            'd90plus' => 'über 90 Tage',
        ],
        'action' => [
            'settle' => 'Ausgleichen',
            'show_entry' => 'Buchung anzeigen',
        ],
        'flash' => [
            'settled' => 'Ausgleich erfasst.',
        ],
    ],

    // Wiederkehrende Vorgänge (Feature 125, MVP-675).
    'recurring' => [
        'title' => 'Wiederkehrende Vorgänge',
        'menu' => 'Wiederkehrend',
        'subtitle' => 'Belegerwartungen, Buchungsvorlagen und Serienrechnungen im Überblick.',
        'principle' => 'Eine Belegerwartung erzeugt keinen Beleg und keine Buchung — erst das Original erfüllt sie. Buchungsvorlagen erzeugen ausschließlich Entwürfe.',
        'invoice_schedules_hint' => 'Serienrechnungen bleiben beim Abrechnungsplan; hier nur zur Übersicht.',
        'preview' => 'Nächste Fälligkeiten: :dates',
        'no_account' => '— kein Konto —',
        'section' => [
            'open_runs' => 'Offene Vorgänge',
            'templates' => 'Vorlagen',
            'invoice_schedules' => 'Serienrechnungen',
        ],
        'column' => [
            'template' => 'Vorlage',
            'period' => 'Periode',
            'expected' => 'Erwartet',
            'name' => 'Bezeichnung',
            'kind' => 'Art',
            'interval' => 'Rhythmus',
            'next_due' => 'Nächste Fälligkeit',
            'responsible' => 'Verantwortlich',
        ],
        'field' => [
            'due_day' => 'Fälligkeitstag',
            'starts_on' => 'Beginn',
            'ends_on' => 'Ende',
        ],
        'hint' => [
            'kind' => 'Belegerwartung wartet auf ein Original; die Buchungsvorlage erzeugt einen Entwurf.',
            'due_day' => '1–28, damit jeder Monat den Tag hat.',
            'accounts' => 'Nur für Buchungsvorlagen — zusammen mit dem erwarteten Betrag.',
        ],
        'action' => [
            'add' => 'Vorlage anlegen',
            'edit' => 'Vorlage bearbeiten',
            'run' => 'Jetzt ausführen',
            'pause' => 'Pausieren',
            'resume' => 'Fortsetzen',
            'end' => 'Beenden',
            'open_schedules' => 'Abrechnungspläne öffnen',
        ],
        'confirm' => [
            'end' => 'Vorlage beenden? Bereits erzeugte Vorgänge bleiben bestehen.',
        ],
        'empty' => [
            'runs' => 'Keine offenen Vorgänge.',
            'templates' => 'Noch keine Vorlage angelegt.',
            'schedules' => 'Kein aktiver Abrechnungsplan.',
        ],
        'flash' => [
            'saved' => 'Vorlage gespeichert.',
            'versioned' => 'Vorlage in neuer Fassung gespeichert.',
            'paused' => 'Vorlage pausiert.',
            'resumed' => 'Vorlage fortgesetzt.',
            'ended' => 'Vorlage beendet.',
            'ran' => 'Lauf ausgeführt.',
        ],
        'error' => [
            'already_closed' => 'Der Vorgang ist bereits abgeschlossen.',
            'template_incomplete' => 'Eine Buchungsvorlage braucht Soll-, Habenkonto und Betrag.',
        ],
        'blocker' => [
            'no_lines' => 'Der Vorlage fehlen Buchungszeilen.',
        ],
        'notification' => [
            'title' => 'Wiederkehrender Vorgang überfällig: :name',
            'message' => 'Fällig am :due — Status: :status.',
        ],
    ],

    // Finanzberichte (Feature 125, MVP-676).
    'reports' => [
        'title' => 'Finanzberichte',
        'menu' => 'Finanzberichte',
        'subtitle' => 'Auswertungen der lokalen Buchhaltung im gewählten Zeitraum.',
        'period' => 'Zeitraum :from – :to',
        'as_of' => 'Stand :date',
        'empty' => 'Keine Daten im Zeitraum.',
        'vat_preview_hint' => 'Prüfbare Vorschau — der MVP übermittelt keine Umsatzsteuer-Voranmeldung an ELSTER.',
        'euer_preview_hint' => 'Vorschau nach Zufluss/Abfluss (§ 11 EStG), gegliedert nach den Zeilen der Anlage EÜR — keine Anlage EÜR.',
        'euer_manual_hint' => 'manuell zu erfassen',
        'pnl_hint' => 'Ergebnis nach Kontengruppen — keine testierte Gewinn- und Verlustrechnung.',
        'column' => [
            'euer_category' => 'EÜR-Zeile',
            'gross' => 'Betrag',
            'deductible' => 'Abziehbar',
            'not_deductible' => 'Nicht abziehbar',
            'opening' => 'Anfangssaldo',
            'closing' => 'Endsaldo',
            'balance' => 'Saldo',
            'direction' => 'Richtung',
            'payable' => 'Zahllast',
            'result' => 'Ergebnis',
            'section' => 'Bereich',
        ],
        'section' => [
            'income' => 'Erträge',
            'expense' => 'Aufwendungen',
            'balances' => 'Bank- und Kassenkonten',
        ],
        'kpi' => [
            'cash' => 'Bank & Kasse',
            'receivable' => 'Forderungen',
            'payable' => 'Verbindlichkeiten',
            'forecast' => 'Vorschau',
            'findings' => 'Befunde',
        ],
        'aging' => [
            'receivable' => 'Altersstruktur Forderungen',
            'payable' => 'Altersstruktur Verbindlichkeiten',
        ],
        'unclear' => [
            'title' => 'Ungeklärte Fälle',
            'none' => 'Keine ungeklärten Fälle.',
            'open_items' => ':count offene Posten sind im Zeitraum noch nicht ausgeglichen.',
            'settlement_without_item' => 'Ausgleich :id ohne zugehörigen offenen Posten.',
            'settlement_without_source' => 'Ausgleich :id ohne auswertbaren Ursprungsbeleg.',
            'account_without_category' => 'Konto :account hat keine EÜR-Zeile.',
        ],
        'quality' => [
            'headline' => 'Was der Auswertung im Weg steht',
            'none' => 'Keine Befunde.',
            'drafts' => ':count Buchungen sind noch nicht festgeschrieben.',
            'unbalanced' => ':count Entwürfe sind nicht ausgeglichen.',
            'blocked_runs' => ':count wiederkehrende Läufe sind blockiert.',
            'open_expectations' => ':count Belegerwartungen sind noch offen.',
            'ten_day_rule' => ':count Zahlungen liegen im Fenster 22.12.–10.01. und gehören belegseitig ins Nachbarjahr (§ 11 Abs. 1 S. 2 EStG).',
            'open_clearing' => ':count Klärungskonten sind noch nicht ausgeglichen.',
            'overdue_filings' => ':count Meldefristen sind überschritten und noch nicht als abgegeben markiert.',
            'kpi' => [
                'drafts' => 'Entwürfe',
                'unbalanced' => 'Unausgeglichen',
                'blocked_runs' => 'Blockierte Läufe',
                'open_expectations' => 'Offene Erwartungen',
            ],
        ],
        'card' => [
            'trial_balance' => [
                'title' => 'Summen- und Saldenliste',
                'text' => 'Vortrag, Bewegung und Saldo je Konto.',
            ],
            'account_ledger' => [
                'title' => 'Kontenblatt',
                'text' => 'Alle Bewegungen eines Kontos mit Drilldown zur Buchung.',
            ],
            'vat' => [
                'title' => 'Umsatzsteuer',
                'text' => 'Umsatzsteuer, Vorsteuer und Zahllast als Vorschau.',
            ],
            'euer' => [
                'title' => 'EÜR-Vorschau',
                'text' => 'Einnahmen und Ausgaben nach Zufluss und Abfluss.',
            ],
            'recapitulative' => [
                'title' => 'Zusammenfassende Meldung',
                'text' => 'Innergemeinschaftliche Lieferungen je USt-IdNr.',
            ],
            'pnl' => [
                'title' => 'Ergebnisrechnung',
                'text' => 'Erträge und Aufwendungen nach Kontengruppen.',
            ],
            'liquidity' => [
                'title' => 'Liquidität',
                'text' => 'Ist-Salden, offene Posten und Vorschau — getrennt ausgewiesen.',
            ],
            'quality' => [
                'title' => 'Buchungsqualität',
                'text' => 'Entwürfe, blockierte Läufe und offene Erwartungen.',
            ],
            'journal' => [
                'title' => 'Journal',
                'text' => 'Alle Festbuchungen in Journalreihenfolge.',
            ],
            'open_items' => [
                'title' => 'Offene Posten',
                'text' => 'Debitoren und Kreditoren mit Altersstruktur.',
            ],
        ],
    ],

    // Periodenabschluss (Feature 125, MVP-677).
    'closing' => [
        'title' => 'Periodenabschluss',
        'menu' => 'Abschluss',
        'subtitle' => 'Perioden vorläufig oder endgültig schließen — und begründet wieder öffnen.',
        'blocked_hint' => 'Der Abschluss bleibt gesperrt, solange ein Punkt rot ist.',
        'reopen_hint' => 'Die Wiedereröffnung hebt eine Festschreibung auf. Sie wird mit Begründung in der Nachweiskette festgehalten.',
        'column' => [
            'period' => 'Periode',
            'closed_at' => 'Geschlossen',
            'reopened' => 'Wiedereröffnet',
        ],
        'field' => [
            'reason' => 'Begründung',
        ],
        'action' => [
            'soft_close' => 'Vorläufig schließen',
            'close' => 'Endgültig schließen',
            'close_submit' => 'Periode schließen',
            'reopen' => 'Wieder öffnen',
            'reopen_submit' => 'Periode öffnen',
            'close_year' => 'Geschäftsjahr schließen',
        ],
        'confirm' => [
            'year' => 'Geschäftsjahr schließen? Alle Perioden müssen geschlossen sein.',
        ],
        'check' => [
            'no_drafts' => 'Keine offenen Entwürfe in der Periode.',
            'drafts' => ':count Buchungen sind noch nicht festgeschrieben.',
            'balanced' => 'Alle Buchungen sind ausgeglichen.',
            'unbalanced' => ':count Buchungen sind nicht ausgeglichen.',
            'sequence_ok' => 'Keine früheren Perioden mehr offen.',
            'earlier_open' => ':count frühere Perioden sind noch offen.',
            'key' => [
                'drafts' => 'Entwürfe',
                'balanced' => 'Ausgleich',
                'sequence' => 'Reihenfolge',
            ],
        ],
        'flash' => [
            'soft_closed' => 'Periode vorläufig geschlossen.',
            'closed' => 'Periode geschlossen.',
            'reopened' => 'Periode wieder geöffnet.',
            'year_closed' => 'Geschäftsjahr geschlossen.',
        ],
        'error' => [
            'reason_required' => 'Für die Wiedereröffnung ist eine Begründung Pflicht.',
            'already_open' => 'Die Periode ist bereits offen.',
            'wrong_status' => 'In diesem Zustand (:status) ist der Schritt nicht möglich.',
            'periods_open' => 'Noch :count Perioden sind nicht geschlossen.',
        ],
    ],

    // Startsalden und DATEV-Übergabe (Feature 125, MVP-677).
    'opening' => [
        'title' => 'Startsalden übernehmen',
        'subtitle' => 'CSV mit Konto, Soll und Haben — erst prüfen, dann buchen.',
        'hint' => 'Der MVP übernimmt Startsaldo, offene Posten und Stichtag; ein vollständiges Alt-Journal wird bewusst nicht importiert.',
        'field' => [
            'file' => 'CSV-Datei',
        ],
        'action' => [
            'dry_run' => 'Probelauf',
            'import' => 'Übernehmen',
        ],
        'flash' => [
            'dry_run' => 'Probelauf: :lines Zeilen, Soll :debit, Haben :credit, :errors Fehler.',
            'imported' => 'Eröffnungsbuchung :no angelegt.',
        ],
        'error' => [
            'missing_account' => 'Zeile :line ohne Konto.',
            'unknown_account' => 'Konto :account (Zeile :line) existiert nicht.',
            'both_sides' => 'Zeile :line trägt Soll und Haben.',
            'unbalanced' => 'Soll (:debit) und Haben (:credit) stimmen nicht überein.',
        ],
    ],
    'datev' => [
        'title' => 'DATEV-Übergabe',
        'subtitle' => 'Buchungszeilen des Zeitraums als CSV.',
        'hint' => 'Erzeugt aus den Festbuchungen — nicht erneut aus den Belegen abgeleitet.',
        'action' => [
            'export' => 'Exportieren',
        ],
    ],

    // Kontenplan-Vorlagen (Feature 125, MVP-678).
    'template' => [
        'title' => 'Kontenplan aus Vorlage',
        'subtitle' => 'Konten, Steuerkennzeichen und Buchungsregeln in einem Zug anlegen.',
        'hint_first' => 'Die Vorlage legt Konten, Steuerkennzeichen und passende Buchungsregeln an — damit ist die Buchungs-Inbox sofort arbeitsfähig.',
        'hint_additive' => 'Es wird nur ergänzt: vorhandene Konten und Regeln bleiben unverändert.',
        'disclaimer' => 'Auszug für den Einstieg in Anlehnung an den jeweiligen Standardkontenrahmen, gültig für Deutschland. Kontenwahl und Steuerzuordnung gehören vor dem ersten Buchen fachlich geprüft.',
        'field' => [
            'template' => 'Vorlage',
        ],
        'action' => [
            'apply' => 'Vorlage anwenden',
        ],
        'flash' => [
            'applied' => 'Vorlage angewendet: :accounts Konten, :tax_codes Steuerkennzeichen, :rules Regeln neu, :skipped übersprungen.',
        ],
        'error' => [
            'unknown' => 'Unbekannte Kontenplan-Vorlage: :code',
        ],
    ],

    // Versteuerungsart (Feature 125, MVP-679).
    'taxation' => [
        'title' => 'Versteuerungsart',
        'subtitle' => 'Soll- oder Ist-Versteuerung — wirkt nur auf die Umsatzsteuer-Auswertung.',
        'current' => 'Aktuell: :method',
        'default_hint' => 'Ohne Festlegung gilt die Soll-Versteuerung (§ 16 Abs. 1 UStG).',
        'field' => [
            'method' => 'Versteuerungsart',
            'valid_from' => 'Gültig ab',
        ],
        'hint' => [
            'method' => 'Die Ist-Versteuerung (§ 20 UStG) ist genehmigungspflichtig; die Vorsteuer bleibt in beiden Fällen unberührt.',
            'valid_from' => 'Üblich ist der Jahreswechsel — vorgeschlagen ist der nächste 1. Januar.',
        ],
        'column' => [
            'changeover' => 'Offene Posten beim Wechsel',
        ],
        'action' => [
            'switch' => 'Versteuerungsart wechseln',
            'switch_submit' => 'Wechsel eintragen',
        ],
        'changeover' => [
            'headline' => ':count offene Posten über :amount sind am Stichtag betroffen.',
            'hint' => '§ 20 S. 3 UStG: Umsätze dürfen weder doppelt erfasst werden noch unversteuert bleiben. Der Wechsel wird nicht blockiert — die Beurteilung gehört zur Steuerberatung.',
            'summary' => ':count Posten / :amount',
        ],
        'flash' => [
            'switched' => 'Versteuerungsart gewechselt.',
        ],
        'error' => [
            'unchanged' => 'Diese Versteuerungsart gilt zum Stichtag bereits.',
            'later_section' => 'Es gibt bereits einen späteren Abschnitt ab :date.',
        ],
    ],
    // Klärungsbuchung und interne Umbuchung (Feature 125, MVP-681).
    'clearing' => [
        'title' => 'Klärungsbuchung',
        'memo' => 'Klärungsfall: :purpose',
        'no_account' => 'Es ist kein Klärungskonto eingerichtet. Ein Konto im Kontenplan als Klärungskonto kennzeichnen.',
        'action' => [
            'post' => 'Auf Klärungskonto buchen',
            'post_submit' => 'Klärungsbuchung anlegen',
        ],
        'field' => [
            'account' => 'Klärungskonto',
            'note' => 'Warum ist der Umsatz unklar?',
            'follow_up_on' => 'Wiedervorlage',
        ],
        'hint' => [
            'account' => 'Nur ausdrücklich gekennzeichnete Klärungskonten stehen zur Auswahl.',
            'note' => 'Pflichtangabe — sie ist der einzige Hinweis, warum hier nicht zugeordnet wurde.',
            'follow_up_on' => 'Bis zu diesem Tag soll der Fall aufgelöst sein.',
        ],
        'error' => [
            'not_a_clearing_account' => 'Das gewählte Konto ist kein Klärungskonto.',
            'note_required' => 'Eine Begründung ist Pflicht.',
        ],
        'blocker' => [
            'unassigned' => 'Kein zugeordneter Beleg — buchbar nur über eine Zuordnung oder das Klärungskonto.',
        ],
        'flash' => [
            'posted' => 'Klärungsbuchung angelegt.',
        ],
    ],
    'transfer' => [
        'title' => 'Interne Umbuchung',
        'action' => [
            'record' => 'Interne Umbuchung',
            'record_submit' => 'Umbuchung festschreiben',
        ],
        'field' => [
            'from_account' => 'Von Konto',
            'to_account' => 'Auf Konto',
        ],
        'hint' => [
            'note' => 'Wofür wurde das Geld bewegt (z. B. Bankabhebung für die Kasse)?',
        ],
        'error' => [
            'same_account' => 'Quell- und Zielkonto müssen sich unterscheiden.',
            'not_a_money_account' => 'Konto :account ist kein Geld-, Kassen- oder Transitkonto.',
            'amount_positive' => 'Der Betrag muss größer als null sein.',
        ],
        'flash' => [
            'recorded' => 'Umbuchung festgeschrieben.',
        ],
    ],

    // Meldepflichten der Umsatzsteuer (Feature 125, MVP-684).
    'filing' => [
        'fields' => [
            'title' => 'Kennziffern der Voranmeldung',
            'subtitle' => 'Zuordnung der Steuerkennzeichen zu den Feldern der UStVA — Abgleichhilfe, kein Vordruck.',
            'tax_codes' => 'Steuerkennzeichen',
            'remaining' => 'Verbleibende Vorauszahlung (83)',
            'unclear' => 'Steuerkennzeichen :code ohne Kennziffer.',
            'column' => [
                'field' => 'Kennziffer',
                'base' => 'Bemessungsgrundlage',
                'tax' => 'Steuerbetrag',
            ],
            'hint' => [
                'base' => 'Feld der Bemessungsgrundlage, z. B. 81 (19 %), 86 (7 %), 41 (i.g. Lieferungen).',
                'tax' => 'Feld des Steuerbetrags, z. B. 66 (Vorsteuer), 61 (i.g. Erwerb).',
            ],
            'flash' => [
                'saved' => 'Kennziffern gespeichert.',
            ],
        ],
        'calendar' => [
            'menu' => 'Steuertermine',
            'title' => 'Steuertermine',
            'subtitle' => 'Fristen der Umsatzsteuer und ihr Erledigungsstand.',
            'hint' => 'Die Fristen sind berechnet (§ 108 Abs. 3 AO: Wochenende und Feiertage verschieben auf den nächsten Werktag). Übermittelt wird nichts.',
            'tax_advised' => 'steuerlich beraten',
            'overdue' => 'Überfällig',
            'empty' => 'Keine Termine im Zeitraum.',
            'column' => [
                'kind' => 'Pflicht',
                'due_on' => 'Frist',
            ],
            'action' => [
                'submitted' => 'Als abgegeben markieren',
            ],
        ],
        'notification' => [
            'title' => ':kind steht an',
            'message' => 'Zeitraum :period — Frist :due.',
        ],
        'no_period' => 'Für diese Organisation ist kein Voranmeldungszeitraum hinterlegt (Kleinunternehmer § 19 UStG).',
        'prepayment_memo' => 'Sondervorauszahlung 1/11 für :year',
        'prepayment' => [
            'title' => 'Sondervorauszahlung buchen',
            'submit' => 'Sondervorauszahlung buchen',
            'calculation' => '1/11 aus :year: Vorjahressteuer :tax, hochgerechnet :annualised → :amount.',
            'annualised_hint' => 'Nur :months Monate im Vorjahr tätig — auf ein volles Jahr hochgerechnet (§ 47 Abs. 3 UStDV).',
            'due_hint' => 'Anmeldung und Zahlung bis :date.',
        ],
        'title' => 'Meldepflichten',
        'subtitle' => 'Voranmeldungszeitraum, Dauerfristverlängerung und Fristen der Umsatzsteuer.',
        'current' => 'Aktuell: :interval',
        'default_hint' => 'Ohne Festlegung gilt das Kalendervierteljahr (§ 18 Abs. 2 S. 1 UStG).',
        'field' => [
            'period' => 'Zeitraum',
            'remaining' => 'Verbleibende Vorauszahlung',
            'prepayment_account' => 'Konto Sondervorauszahlung',
            'money_account' => 'Geldkonto',
            'interval' => 'Voranmeldungszeitraum',
            'valid_from' => 'Gültig ab',
            'year' => 'Kalenderjahr',
            'granted_on' => 'Genehmigt am',
            'special_prepayment' => 'Sondervorauszahlung (1/11)',
        ],
        'hint' => [
            'prepayment_account' => 'Üblich: 1781 (SKR03) bzw. 3830 (SKR04) — Umsatzsteuer-Vorauszahlungen 1/11.',
            'interval' => 'Über den Zeitraum entscheidet das Finanzamt — das Programm führt die Entscheidung nach.',
            'valid_from' => 'In der Regel ein Jahreswechsel; ein unterjähriger Wechsel ist möglich.',
            'granted_on' => 'Leer lassen, solange die Verlängerung nicht bewilligt ist.',
            'special_prepayment' => '1/11 der Vorauszahlungen des Vorjahres; Anmeldung und Zahlung bis 10.02. (§ 47 UStDV).',
        ],
        'action' => [
            'switch' => 'Zeitraum wechseln',
            'switch_submit' => 'Zeitraum übernehmen',
        ],
        'error' => [
            'note_required' => 'Für „nicht erforderlich" ist eine Begründung Pflicht.',
            'amount_positive' => 'Der Betrag muss größer als null sein.',
            'not_a_money_account' => 'Das gewählte Konto ist kein Geld- oder Kassenkonto.',
            'no_extension' => 'Für :year ist keine Dauerfristverlängerung erfasst.',
            'unchanged' => 'Dieser Voranmeldungszeitraum gilt zum Stichtag bereits.',
            'later_section' => 'Es gibt bereits einen Abschnitt ab :date. Zuerst diesen bearbeiten.',
        ],
        'flash' => [
            'marked' => 'Erledigung festgehalten.',
            'prepayment_posted' => 'Sondervorauszahlung gebucht.',
            'switched' => 'Voranmeldungszeitraum gewechselt.',
            'extension_saved' => 'Dauerfristverlängerung gespeichert.',
        ],
        'suggestion' => [
            'headline' => 'Vorschlag aus dem Vorjahr :year (Steuer :amount): :interval.',
            'monthly' => 'Über 9.000 € Vorjahressteuer — monatlich (§ 18 Abs. 2 S. 2 UStG).',
            'quarterly' => 'Zwischen 2.000 € und 9.000 € — Kalendervierteljahr (§ 18 Abs. 2 S. 1 UStG).',
            'annual' => 'Bis 2.000 € — Befreiung von der Voranmeldung möglich (§ 18 Abs. 2 S. 3 UStG).',
            'none' => 'Keine Umsatzsteuervoranmeldung (Kleinunternehmer § 19 UStG).',
            'founder_rule' => 'Ab dem Besteuerungszeitraum 2027 gilt für Neugründungen wieder die monatliche Abgabepflicht.',
        ],
        'extension' => [
            'short' => 'mit Dauerfristverlängerung',
            'title' => 'Dauerfristverlängerung',
            'active' => 'Dauerfristverlängerung seit :year',
            'no_prepayment' => 'Vierteljahreszahler erhalten die Verlängerung ohne Sondervorauszahlung (§ 46 UStDV).',
            'prepayment_note' => 'Sondervorauszahlung :amount für :year erfasst.',
        ],
    ],

    // Zusammenfassende Meldung (Feature 125, MVP-687).
    'recapitulative' => [
        'title' => 'Zusammenfassende Meldung',
        'hint' => 'Meldung nach § 18a UStG. Die Dauerfristverlängerung gilt hier NICHT — die Frist bleibt der 25. Tag nach dem Meldezeitraum.',
        'due' => 'Frist: :date',
        'interval' => 'Meldezeitraum: :interval',
        'total' => 'Innergemeinschaftliche Lieferungen',
        'column' => [
            'vat_id' => 'USt-IdNr.',
        ],
        'unclear' => [
            'missing_vat_id' => 'Buchung :entry (:customer) ohne USt-IdNr. des Empfängers.',
            'unknown_customer' => 'ohne Kunde',
        ],
    ],

];
