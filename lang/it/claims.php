<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : claims.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'pattern' => [
        'title' => 'Schemi rilevanti (difetti di serie, lotti)',
        'hint' => 'Gruppi con almeno :threshold reclami nel periodo. Un’indicazione, non una decisione.',
        'none' => 'Nessuno schema rilevante nel periodo.',
        'rule_label' => 'Regola',
        'group' => 'Gruppo',
        'cases' => 'Casi',
        'count' => 'Numero',
        'rule' => [
            'lot' => 'Lotto',
            'article_defect' => 'Articolo × tipo di difetto',
            'article_cause' => 'Articolo × causa',
            'supplier_defect' => 'Fornitore × tipo di difetto',
            'entry_type_cause' => 'Tipo di ordine × causa',
        ],
        'notify_title' => 'Schema di reclami rilevante: :label',
        'notify_message' => ':count reclami in :days giorni (:rule).',
    ],
    // Retourenlabel einer RMA (MVP-917).
    'return_label' => [
        'title' => 'Etichetta di reso',
        'create' => 'Crea etichetta di reso',
        'download' => 'Scarica etichetta',
        'created' => 'Etichetta di reso creata (spedizione :tracking).',
        'no_address' => 'L\'etichetta di reso richiede l\'indirizzo del cliente (via, CAP, città).',
    ],
    // Retourenanmeldung im Kundenportal (MVP-935).
    'portal_return' => [
        'list' => [
            'claim' => 'Reclamo',
            'empty' => 'Nessun reso ancora registrato.',
            'rma' => 'Numero di reso',
            'status' => 'Stato',
            'title' => 'I miei resi',
        ],
        'capability' => 'Registrare un reso',
        'nav' => 'Registrare un reso',
        'title' => 'Registrare un reso',
        'intro' => 'Scelga la consegna o l\'oggetto, descriva il motivo e alleghi foto se necessario. Riceverà un numero di reso; se serve forniamo un\'etichetta di reso.',
        'empty' => 'Non ci sono consegne né oggetti per il suo account.',
        'submit' => 'Registra il reso',
        'label' => 'Scarica l\'etichetta di reso',
        'field' => [
            'delivery' => 'Consegna',
            'asset' => 'Oggetto',
            'serial_no' => 'Numero di serie',
            'quantity' => 'Quantità',
            'title' => 'Descrizione breve',
            'description' => 'Motivo del reso',
            'photos' => 'Foto o documenti (max. 5)',
        ],
        'flash' => [
            'submitted' => 'Reso registrato: reclamo :number, numero di reso :rma.',
        ],
        'error' => [
            'subject' => 'Scelga una consegna o un oggetto.',
            'serial' => 'Questo numero di serie non appartiene alla consegna scelta.',
        ],
    ],
    // Nachreichungen aus dem Kundenportal.
    'portal_note' => [
        'history' => 'Le Sue integrazioni successive',
        'notification_title' => 'Integrazione successiva al reclamo :number',
    ],
];
