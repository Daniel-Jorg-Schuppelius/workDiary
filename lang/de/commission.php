<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : commission.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Provisionen (Feature 146, MVP-729). Bewusst ohne Auszahlung: WorkDiary
// rechnet und exportiert, gezahlt wird in der Lohnabrechnung.
return [
    'title' => 'Provisionen',

    'page' => [
        'agents' => 'Vermittler',
        'rules' => 'Provisionsregeln',
        'runs' => 'Provisions-Abrechnungsläufe',
    ],

    'subtitle' => [
        'agents' => 'Externe Vermittler ohne Benutzerkonto als Provisionsempfänger — ausgezahlt wird außerhalb.',
        'index' => 'Provisionszeilen je Beleg. Grundlage ist die bezahlte Rechnung — nie die ausgestellte.',
        'rules' => 'Satz je Lead-Quelle, Produktgruppe oder Vertriebsperson. Je Beleg gewinnt genau eine Regel.',
        'runs' => 'Periode abrechnen: Entwurf ist Vorschau, Schließen schreibt fest. Danach nur noch Rückrechnung.',
    ],

    'section' => [
        'unassigned' => 'Bezahlte Rechnungen ohne Provision',
        'per_user' => 'Summen je Vertriebsperson',
        'run_rows' => 'Provisionszeilen des Laufs',
    ],

    'group' => [
        'tiers' => 'Staffel, Deckel, Auszahlung',
        'rule' => 'Regel',
        'validity' => 'Gültigkeit',
        'period' => 'Periode',
    ],

    'action' => [
        'to_agents' => 'Vermittler',
        'create_agent' => 'Vermittler anlegen',
        'edit_agent' => 'Vermittler bearbeiten',
        'create_rule' => 'Regel anlegen',
        'edit_rule' => 'Regel bearbeiten',
        'edit' => 'Bearbeiten',
        'delete' => 'Löschen',
        'save' => 'Speichern',
        'show' => 'Ansehen',
        'export' => 'CSV-Export',
        'close' => 'Lauf schließen',
        'back' => 'Zurück',
        'assign' => 'Vertriebsperson zuordnen',
        'create_run' => 'Abrechnungslauf anlegen',
        'to_rules' => 'Regeln',
        'to_runs' => 'Abrechnungsläufe',
        'to_commissions' => 'Provisionszeilen',
    ],

    'field' => [
        'agent' => 'Vermittler',
        'agent_name' => 'Name',
        'company' => 'Firma',
        'email' => 'E-Mail',
        'tier_period' => 'Staffelzeitraum',
        'annual_cap_amount' => 'Jahresdeckel',
        'tier_threshold' => ':n. Stufe ab Umsatz',
        'tier_rate' => ':n. Stufe Satz (%)',
        'liability_days' => 'Haftungsfrist (Tage)',
        'is_partial_accrual' => 'Schon auf Teilzahlungen',
        'name' => 'Bezeichnung',
        'scope' => 'Geltungsbereich',
        'scope_value' => 'Bereichswert',
        'user' => 'Vertriebsperson',
        'rate_percent' => 'Satz',
        'priority' => 'Priorität',
        'valid_from' => 'Gültig ab',
        'valid_to' => 'Gültig bis',
        'validity' => 'Gültigkeit',
        'is_active' => 'Aktiv',
        'note' => 'Notiz',
        'status' => 'Status',
        'invoice' => 'Beleg',
        'customer' => 'Kunde',
        'earned_on' => 'Stichtag',
        'base_amount' => 'Bemessungsgrundlage',
        'commission_amount' => 'Provision',
        'run' => 'Lauf',
        'period' => 'Periode',
        'period_start' => 'Periode von',
        'period_end' => 'Periode bis',
        'currency' => 'Währung',
        'entry_count' => 'Zeilen',
        'total_base' => 'Summe Grundlage',
        'total_commission' => 'Summe Provision',
        'closed_by' => 'Geschlossen von',
        'paid_on' => 'Bezahlt am',
    ],

    'scope' => [
        'agent' => 'Vermittler',
        'all' => 'Alle Belege',
        'lead_source' => 'Lead-Quelle',
        'product_group' => 'Produktgruppe',
        'user' => 'Vertriebsperson',
    ],

    'tier_period' => [
        'none' => 'Keine Staffel',
        'month' => 'Monat',
        'quarter' => 'Quartal',
        'year' => 'Jahr',
    ],
    'status' => [
        'pending' => 'Offen',
        'settled' => 'Abgerechnet',
        'reversed' => 'Zurückgerechnet',
    ],

    'run_status' => [
        'draft' => 'Entwurf',
        'closed' => 'Geschlossen',
    ],

    'assignment' => [
        'lead' => 'Aus der Lead-Pipeline',
        'manual' => 'Manuell zugeordnet',
    ],

    'badge' => [
        'reversal' => 'Rückrechnung',
    ],

    'empty' => [
        'agents' => 'Noch keine Vermittler angelegt.',
        'rules' => 'Noch keine Provisionsregel angelegt.',
        'commissions' => 'Noch keine Provisionszeile vorhanden.',
        'runs' => 'Noch kein Abrechnungslauf angelegt.',
        'run_rows' => 'Keine Provisionszeilen in dieser Periode.',
    ],

    'hint' => [
        'agent' => 'Statt einer Person: Provision an einen externen Vermittler.',
        'rule_agent' => 'Nur mit Geltungsbereich „Vermittler“.',
        'tiers' => 'Staffel: Ab dem erreichten Umsatz im Zeitraum gilt der Satz der höchsten Stufe für den neuen Betrag; der Jahresdeckel begrenzt die Provision je Kalenderjahr.',
        'liability_days' => 'Erst nach Ablauf auszahlbar — die Zeile fällt in den Abrechnungslauf dieser Periode.',
        'partial_accrual' => 'Provision anteilig je Zahlungseingang statt erst bei vollständiger Zahlung.',
        'scope_value' => 'Nur bei Geltungsbereich Lead-Quelle oder Produktgruppe; muss zum gewählten Bereich passen.',
        'user' => 'Nur bei Geltungsbereich Vertriebsperson.',
        'priority' => 'Höhere Zahl gewinnt; bei Gleichstand entscheidet der engere Geltungsbereich.',
        'period' => 'Sprechende Bezeichnung, z. B. 2026-08. Leer = aus dem Startdatum abgeleitet.',
        'currency' => 'Ein Lauf rechnet genau eine Währung ab — Provisionen werden nie umgerechnet.',
        'assign' => 'Leer lassen = wieder die Herkunft aus der Lead-Pipeline verwenden.',
        'current_assignment' => 'Aktuell zuständig: :user (:source).',
        'no_assignment' => 'Aktuell ist niemand zuständig — ohne Zuordnung entsteht keine Provision.',
        'unassigned' => 'Diese Rechnungen sind bezahlt, aber niemandem zugeordnet: weder manuell noch über einen konvertierten Lead.',
        'draft_preview' => 'Entwurf: Die Zeilen werden bei jedem Aufruf neu ermittelt. Erst das Schließen schreibt sie fest.',
        'no_payout' => 'WorkDiary rechnet die Provision aus und exportiert sie — die Auszahlung erfolgt in der Lohnabrechnung.',
    ],

    'confirm' => [
        'delete_rule' => 'Provisionsregel löschen? Bereits berechnete Provisionen bleiben unverändert.',
        'delete_run' => 'Entwurf des Abrechnungslaufs löschen?',
        'close_run' => 'Lauf schließen? Danach ist er festgeschrieben; Korrekturen laufen nur noch über eine Rückrechnung.',
    ],

    'flash' => [
        'agent_saved' => 'Vermittler wurde gespeichert.',
        'rule_created' => 'Provisionsregel angelegt.',
        'rule_updated' => 'Provisionsregel gespeichert.',
        'rule_deleted' => 'Provisionsregel gelöscht.',
        'assigned' => 'Zuordnung gespeichert.',
        'run_created' => 'Abrechnungslauf angelegt.',
        'run_closed' => 'Abrechnungslauf geschlossen und festgeschrieben.',
        'run_deleted' => 'Abrechnungslauf gelöscht.',
    ],

    'error' => [
        'period_reversed' => 'Das Ende der Periode liegt vor ihrem Beginn.',
        'period_overlap' => 'Für diesen Zeitraum gibt es bereits einen Abrechnungslauf.',
        'already_closed' => 'Dieser Abrechnungslauf ist bereits geschlossen.',
    ],

    'note' => [
        'capped' => 'Jahresdeckel :cap erreicht — Provision gekürzt.',
        'credit_note' => 'Rückrechnung wegen Gutschrift :number',
        'cancelled' => 'Rückrechnung wegen Storno',
        'reassigned' => 'Rückrechnung wegen Neuzuordnung der Vertriebsperson',
        'payment_reverted' => 'Rückrechnung wegen zurückgenommener Zahlung',
    ],
    'reversal_kind' => [
        'credit_note' => 'Gutschrift',
        'cancellation' => 'Storno',
        'reassignment' => 'Neuzuordnung',
        'payment' => 'Zahlung zurückgenommen',
    ],

    'export' => [
        'period' => 'Periode',
        'user' => 'Vertriebsperson',
        'invoice' => 'Beleg',
        'customer' => 'Kunde',
        'earned_on' => 'Stichtag',
        'currency' => 'Waehrung',
        'base' => 'Bemessungsgrundlage',
        'rate' => 'Satz in Prozent',
        'commission' => 'Provision',
        'kind' => 'Art',
        'note' => 'Notiz',
        'reversal' => 'Rueckrechnung',
        'regular' => 'Provision',
    ],
];
