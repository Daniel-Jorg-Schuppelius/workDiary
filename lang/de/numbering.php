<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : numbering.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Vergabe von Kunden- und Lieferantennummern durch ein Buchhaltungsprogramm (Phase 141, MVP-1130–1134),
// Seite „Nummernkreise“ (Phase 144, MVP-1147–1149).
return [
    'source' => [
        'local' => 'workDiary',
        'inactive' => ':program (nicht aktiv – vergibt workDiary)',
        'column' => 'Vergibt',
        'page_hint' => 'Vergibt ein Buchhaltungsprogramm die Kunden- oder Lieferantennummern, bekommt ein neuer Datensatz seine Nummer beim Übertragen. Bestehende Nummern bleiben unverändert. Rechnungs-, Gutschrift- und Stornonummern vergibt das Programm des Fakturierungswegs oder Lexoffice, wenn dort der Schalter gesetzt ist. Vergibt ein Programm, ist das Format gesperrt.',
        'billing' => ':program (Fakturierungsweg)',
        'deviation' => ':program für einen Kunden|:program für :count Kunden',
    ],
    'field' => [
        'locked_hint' => 'Vergibt :program beim Übertragen.',
        'row' => ':field (:scope)',
    ],
    'separators' => [
        'prefix' => 'Trenner nach Präfix, alle Kreise',
        'year' => 'Trenner nach Jahr, alle Kreise',
        'apply' => 'Für alle Kreise übernehmen',
        'hint' => 'Übernimmt beide Trenner in alle Kreise, die workDiary vergibt, und speichert die Seite. Ein leeres Feld bedeutet: kein Trenner. Vergebene Nummern bleiben unverändert.',
    ],
    'preview' => [
        'program' => 'Legt :program fest',
        'locked_hint' => 'Format und Nummer legt :program fest.',
        'next_remote' => 'Nächste in :program: :number',
        'last_adopted' => 'Zuletzt von :program: :number',
        'pending' => 'Ein Kontakt wartet auf seine Nummer.|:count Kontakte warten auf ihre Nummer.',
    ],
    'issue' => [
        'no_issuer' => ':program kann :scope nicht übertragen. Stellen Sie auf der Seite „Nummernkreise“ ein, wer die Nummer vergibt.',
        'quote_pending' => 'Die Angebotsnummer vergibt :program beim Versand; bis dahin trägt das Angebot eine Entwurfsnummer.',
        'draft_number' => 'Dieser Beleg trägt eine Entwurfsnummer, und :program kann ihn nicht übernehmen. Stellen Sie auf der Seite „Nummernkreise“ ein, wer die Nummer vergibt.',
        'transfer_failed' => 'Übertragung an :program fehlgeschlagen; der Beleg bleibt Entwurf. :error',
        'issue_first' => '„:document“ trägt noch eine Entwurfsnummer. Stellen Sie den Beleg zuerst – dabei vergibt das Buchhaltungsprogramm die Nummer.',
        'invoice_pending' => 'Die Nummer vergibt :program beim Stellen; WorkDiary überträgt den Beleg dabei. Bis dahin trägt er eine Entwurfsnummer.',
    ],
    'flash' => [
        'saved' => 'Ein Nummernkreis gespeichert.|:count Nummernkreise gespeichert.',
        'unchanged' => 'Keine Änderungen.',
        'transfer_pending' => 'Beleg „:document“ angelegt. Beim Stellen überträgt WorkDiary ihn an :program; dort bekommt er auch seine Nummer.',
        'issued' => 'Nummer :number von :program übernommen.',
        'pending' => 'Die Nummer von :program folgt beim nächsten Übertragen: :reason',
        'conflict' => 'Die Nummer :number von :program ist schon an :holder vergeben. Den Fall finden Sie in der Zuordnungs-Inbox.',
    ],
    'conflict' => [
        'title' => 'Nummer :number aus :program ist schon an :holder vergeben',
    ],
    'error' => [
        'taken_remote' => 'Die Nummer :number ist in :program schon an „:name“ vergeben.',
        'taken_local' => 'Die Nummer :number ist schon an :holder vergeben. Bitte ändern Sie dort zuerst die Nummer.',
        'no_number' => ':program hat keine Nummer zurückgegeben.',
    ],
];
