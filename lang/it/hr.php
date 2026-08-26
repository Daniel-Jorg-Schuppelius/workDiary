<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : hr.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Fascicolo personale digitale (Feature 141, MVP-708).
    'personnel_file' => [
        'title' => 'Fascicolo personale',
        'title_mine' => 'Il mio fascicolo personale',
        'nav' => 'Il mio fascicolo personale',
        'subtitle' => 'Fascicolo personale di :name — riservato, visibile solo alla cerchia HR e alla persona interessata.',
        'subtitle_mine' => 'Il tuo fascicolo personale (accesso personale, sola lettura).',
        'back' => 'Torna all\'elenco del personale',
        'empty' => 'Nessun documento nel fascicolo personale.',
        'confidential_fixed' => 'I fascicoli personali sono sempre riservati — l\'interruttore è omesso, il contrassegno è imposto.',
        'retention_pending' => 'dalla cessazione',
        'confirm_delete' => 'Distruggere definitivamente questo documento dal fascicolo personale? File e versioni vengono eliminati; il registro di audit rimane.',
        'field' => [
            'title' => 'Titolo',
            'category' => 'Categoria',
            'validity' => 'Validità',
            'valid_from' => 'Valido dal',
            'valid_until' => 'Valido fino al',
            'retention_until' => 'Conservazione fino al',
            'version' => 'Versione',
            'updated_at' => 'Aggiornato',
            'description' => 'Descrizione',
            'file' => 'File',
            'version_note' => 'Nota di versione',
            'documents' => 'Documenti',
        ],
        'action' => [
            'upload' => 'Aggiungi documento',
            'edit' => 'Modifica',
            'save' => 'Salva',
            'download' => 'Scarica',
            'versions' => 'Versioni',
            'delete' => 'Distruggi',
        ],
        'flash' => [
            'created' => 'Il documento è stato aggiunto al fascicolo personale.',
            'updated' => 'Il documento del fascicolo personale è stato aggiornato.',
        ],
    ],
];
