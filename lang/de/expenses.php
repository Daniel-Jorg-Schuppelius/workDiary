<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : expenses.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'receipt' => [
        'no_vendor' => 'Ohne Anbieter',
        'link_title' => 'Buchungsbeleg',
        'link' => 'Zuordnen',
        'unlink' => 'Zuordnung lösen',
        'unlink_confirm' => 'Zuordnung zum Buchungsbeleg wirklich lösen? Die Auslage zählt danach wieder als eigener Aufwand.',
        'suggestions_hint' => 'Belege mit gleichem Betrag im Zeitfenster. Zuordnen bestätigt, dass es derselbe Vorgang ist — die Auslage zählt dann nicht mehr doppelt.',
        'no_suggestions' => 'Kein passender Beleg gefunden',
        'no_suggestions_hint' => 'Ohne Zuordnung wird die Auslage getrennt als interne Auslage ausgewiesen.',
        'linked' => 'Beleg :number zugeordnet.',
        'unlinked' => 'Zuordnung gelöst.',
        'title' => 'Belegdatei',
        'hint' => 'Hinterlege die Quittung zur Auslage — ohne Beleg ist sie weder für sich prüfbar noch später in die Buchhaltung übernehmbar.',
    ],
    'title' => [
        'index'        => 'Spesen',
        'create'       => 'Spese erfassen',
        'edit'         => 'Spese bearbeiten',
        'inbox'        => 'Spesen-Genehmigung',
        'category_index'  => 'Spesenkategorien',
        'category_create' => 'Spesenkategorie anlegen',
        'category_edit'   => 'Spesenkategorie bearbeiten',
    ],

    'intro' => [
        'category' => 'Spesenkategorien gliedern Belege (z. B. Verpflegung, Übernachtung, Bewirtung) und steuern Voreinstellungen wie Steuersatz, Pflicht zum Beleg-Upload und ob die Spese standardmäßig an Kunden weiterverrechnet wird. Symbol und Farbe bestimmen die Darstellung in Listen und Auswertungen.',
    ],

    'field' => [
        'label'             => 'Bezeichnung',
        'slug'              => 'Slug',
        'icon'              => 'Icon (Material Symbol)',
        'color'             => 'Farbe',
        'description'       => 'Beschreibung',
        'sort'              => 'Sortierung',
        'is_active'         => 'Aktiv',
        'default_tax_rate'  => 'Steuersatz (Default, %)',
        'requires_receipt'  => 'Beleg-Upload verpflichtend',
        'default_billable'  => 'Standardmäßig an Kunden weiterverrechenbar',
        'date'              => 'Belegdatum',
        'category'          => 'Kategorie',
        'vendor'            => 'Händler / Anbieter',
        'amount_gross'      => 'Brutto-Betrag',
        'amount_net'        => 'Netto-Betrag',
        'tax_rate'          => 'Steuersatz (%)',
        'tax_amount'        => 'Steuerbetrag',
        'currency'          => 'Währung',
        'payment_method'    => 'Zahlungsweise',
        'project'           => 'Projekt',
        'customer'          => 'Kunde',
        'task'              => 'Aufgabe',
        'billable'          => 'An Kunden weiterverrechenbar',
        'notes'             => 'Notizen',
        'status'            => 'Status',
        'attachments'       => 'Belege',
        'reimbursement_reference' => 'Erstattungs-Referenz',
        'reject_reason'     => 'Ablehnungsgrund',
        'decided_at'        => 'Entschieden am',
        'reimbursed_at'     => 'Erstattet am',
    ],

    'action' => [
        'create_category' => 'Kategorie anlegen',
        'create'   => 'Spese erfassen',
        'submit'   => 'Zur Genehmigung senden',
        'approve'  => 'Genehmigen',
        'reject'   => 'Ablehnen',
        'cancel'   => 'Stornieren',
        'reimburse' => 'Als erstattet markieren',
        'export'   => 'CSV exportieren',
    ],

    'help' => [
        'color'          => 'Bestimmt die Akzentfarbe für Icon, Badge und Hervorhebungen in Listen.',
        'gross_first'    => 'Trage den Brutto-Wert vom Beleg ein. Netto- und Steuerbetrag werden automatisch berechnet.',
        'requires_receipt' => 'Wenn aktiv, ist beim Erfassen mindestens ein Beleg-Anhang (Foto/PDF) Pflicht.',
    ],

    'empty' => [
        'categories' => 'Keine Spesenkategorien vorhanden.',
        'expenses'   => 'Noch keine Spesen erfasst.',
    ],

    'confirm' => [
        'delete_category' => 'Spesenkategorie wirklich löschen?',
        'delete_expense'  => 'Spese wirklich löschen?',
    ],
];
