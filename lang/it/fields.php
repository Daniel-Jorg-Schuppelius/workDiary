<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : fields.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Feldschema-Baustein (MVP-866): Validierung der Definitionen, Anzeigewerte.
return [
    'validation' => [
        'invalid_row' => 'La definizione del campo nella riga :row non è valida.',
        'label_required' => 'Il campo :row richiede un’etichetta (max. 160 caratteri).',
        'unknown_type' => 'Il campo :row ha un tipo sconosciuto.',
        'invalid_key' => 'La chiave del campo «:key» non è valida (minuscole, cifre, trattini bassi).',
        'duplicate_key' => 'La chiave del campo «:key» è duplicata.',
        'select_needs_options' => 'Il campo a scelta «:label» richiede almeno un’opzione.',
        'fields_required' => 'È richiesto almeno un campo.',
        'too_many_fields' => 'Al massimo :max campi.',
        'range_invalid' => 'Campo «:label»: il minimo non può superare il massimo.',
        'condition_unknown_field' => 'La condizione del campo «:label» fa riferimento a un campo sconosciuto «:field».',
        'condition_cycle' => 'Le condizioni formano un ciclo (il campo «:field» dipende indirettamente da sé stesso).',
    ],
    'value' => [
        'yes' => 'Sì',
        'no' => 'No',
        'signed' => 'Firmato',
        'attachments' => '{1} :count allegato|[2,*] :count allegati',
    ],
    'action' => [
        'clear_signature' => 'Cancella firma',
    ],
    'custom' => [
        'listed' => "Mostra nell'elenco",
        'title' => 'Campi personalizzati',
        'legend' => 'Campi aggiuntivi',
        'subject' => 'Oggetto',
        'fields' => 'Campi',
        'status' => 'Stato',
        'active' => 'Attivo',
        'inactive' => 'Disattivato',
        'none' => 'Nessun campo definito finora.',
        'edit' => 'Modifica campi',
        'save' => 'Salva',
        'activate' => 'Attiva',
        'deactivate' => 'Disattiva',
        'values_count' => ':count record con valori',
        'version' => 'Versione schema :version',
        'saved' => 'Campi personalizzati salvati.',
        'toggled' => 'Stato modificato.',
        'intro' => 'Un elenco di campi per oggetto; i campi compaiono nel modulo, nella pagina di dettaglio e nelle esportazioni. Disattivi invece di eliminare quando esistono già valori.',
        'validation' => [
            'too_many_listed' => "Al massimo :max campi possono comparire nell'elenco.",
            'unknown_subject' => 'Questo oggetto non ha campi personalizzati.',
            'type_not_allowed' => 'Campo «:label»: campi file, foto e firma non sono disponibili qui.',
        ],
    ],
];
