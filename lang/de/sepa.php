<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sepa.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

return [
    'title' => 'Zahlläufe',
    'subtitle' => 'Sammelüberweisungen und Sammeleinzüge als SEPA-Datei',
    'empty' => 'Noch kein Zahllauf angelegt.',
    'no_items' => 'Keine Positionen im Lauf.',
    'run_created' => 'Zahllauf angelegt.',
    'run_released' => 'Zahllauf freigegeben.',
    'run_cancelled' => 'Zahllauf storniert.',
    'item_removed' => 'Position entfernt.',
    'item_adjusted' => 'Zahlbetrag angepasst.',
    'confirm_release' => 'Zahllauf mit :count Positionen freigeben?',
    'confirm_cancel' => 'Zahllauf stornieren? Die Rechnungen werden wieder zahlbar.',
    'released_by' => 'Freigegeben von',
    'file_hash' => 'Datei-Hash (SHA-256)',
    'execution_hint' => 'Vorschlagsdatum; die Bank führt frühestens an diesem Tag aus.',
    'discount_used' => 'Skonto :percent %',
    'adjust_hint' => 'Rechnungsbetrag: :gross. Ein geringerer Zahlbetrag braucht einen Grund.',
    'reference' => 'Rechnung :number',
    'reference_unknown' => 'Rechnung ohne Nummer',
    'document_description' => 'SEPA-Datei des Zahllaufs :id',

    'proposal' => [
        'title' => 'Zahlungsvorschlag',
        'subtitle' => 'Freigegebene Eingangsrechnungen mit wirtschaftlichstem Ausführungsdatum',
        'empty' => 'Keine offenen, zur Zahlung freigegebenen Rechnungen.',
    ],

    'action' => [
        'proposal' => 'Zahlungsvorschlag',
        'create_run' => 'Zahllauf anlegen',
        'show' => 'Ansehen',
        'release' => 'Freigeben',
        'export' => 'SEPA-Datei',
        'cancel' => 'Stornieren',
        'adjust' => 'Betrag anpassen',
        'remove_item' => 'Position entfernen',
    ],

    'column' => [
        'label' => 'Bezeichnung',
        'kind' => 'Art',
        'account' => 'Bankkonto',
        'execution_date' => 'Ausführung',
        'positions' => 'Positionen',
        'total' => 'Summe',
        'status' => 'Status',
        'creditor' => 'Empfänger',
        'invoice_number' => 'Rechnung',
        'due_date' => 'Fällig',
        'execute_on' => 'Zahlen am',
        'gross' => 'Rechnungsbetrag',
        'amount' => 'Zahlbetrag',
        'note' => 'Hinweis',
        'reference' => 'Verwendungszweck',
        'deduction' => 'Abzug',
    ],

    'status' => [
        'draft' => 'Entwurf',
        'released' => 'freigegeben',
        'exported' => 'exportiert',
        'cancelled' => 'storniert',
    ],

    'blocked' => [
        'missing_iban' => 'IBAN fehlt',
        'zero_amount' => 'Betrag 0',
    ],

    'error' => [
        'no_positions' => 'Der Zahllauf enthält keine Positionen.',
        'not_draft' => 'Der Zahllauf ist kein Entwurf mehr.',
        'not_released' => 'Der Zahllauf ist nicht freigegeben.',
        'exported_final' => 'Ein exportierter Zahllauf wird nicht mehr storniert.',
        'invalid_amount' => 'Der Zahlbetrag muss über 0 und darf nicht über dem Rechnungsbetrag liegen.',
        'reason_required' => 'Ein gekürzter Zahlbetrag braucht einen Grund.',
        'zero_amount' => 'Der Betrag muss über 0 liegen.',
        'account_without_iban' => 'Für das gewählte Bankkonto ist keine IBAN hinterlegt.',
        'missing_creditor_id' => 'Es ist keine Gläubiger-Identifikationsnummer hinterlegt (Einstellung finance.sepa_creditor_id).',
        'mandate_unusable' => 'Das Mandat ist widerrufen oder seit über 36 Monaten ungenutzt.',
        'item_without_mandate' => 'Eine Einzugsposition ohne Mandat kann nicht ausgegeben werden.',
        'unavailable' => 'Der SEPA-Export ist in dieser Installation nicht freigeschaltet. Freischaltung über :contact.',
    ],

    'mandate' => [
        'title' => 'SEPA-Mandate',
        'subtitle' => 'Lastschriftmandate der Kunden',
        'empty' => 'Noch kein Mandat erfasst.',
        'created' => 'Mandat angelegt.',
        'revoked' => 'Mandat widerrufen.',
        'confirm_revoke' => 'Mandat widerrufen? Ein Einzug ist danach nicht mehr zulässig.',
        'not_usable' => 'nicht einziehbar',
        'reference_hint' => 'Eindeutig je Gläubiger; erscheint auf dem Kontoauszug des Kunden.',

        'action' => [
            'create' => 'Mandat erfassen',
            'revoke' => 'Widerrufen',
        ],

        'column' => [
            'reference' => 'Mandatsreferenz',
            'customer' => 'Kunde',
            'kind' => 'Art',
            'signed_on' => 'Unterschrieben am',
            'last_collected_on' => 'Letzter Einzug',
            'status' => 'Status',
            'iban' => 'IBAN',
            'bic' => 'BIC',
            'account_holder' => 'Kontoinhaber',
            'note' => 'Notiz',
        ],
    ],
];
