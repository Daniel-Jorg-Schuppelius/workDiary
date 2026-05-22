<?php

return [
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
        'reimburse'=> 'Als erstattet markieren',
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
