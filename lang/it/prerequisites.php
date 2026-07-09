<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : prerequisites.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'blocked' => [
        'missing_required' => 'Prerequisito mancante',
        'missing_optional' => 'Avviso',
        'not_licensed' => 'Non licenziato',
        'not_allowed' => 'Nessuna autorizzazione',
        'provider_unsupported' => 'Non supportato dal provider',
    ],
    'contact_role' => 'Si prega di contattare: :role',
    'warehouses' => [
        'missing' => 'Conteggio e registrazione richiedono almeno un magazzino.',
        'cta' => 'Gestisci magazzini',
    ],
    'dispatch' => [
        'cta' => "Vai al pannello di disposizione dell'ordine",
    ],
    'mappings' => [
        'hint' => "Le corrispondenze vengono create automaticamente durante l\'importazione o la risoluzione degli elementi della inbox (sincronizzazione plugin e importazione CSV).",
        'cta' => 'Vai alla inbox delle integrazioni',
    ],
    'shift_types' => [
        'missing' => 'Non sono ancora stati creati tipi di turno — senza tipo la pianificazione dei turni è limitata.',
        'cta' => 'Crea tipi di turno',
        'dialog_hint' => 'Nessun tipo di turno disponibile. Il turno viene salvato senza tipo; l\'amministrazione gestisce i tipi tramite «Tipi di turno» nel piano turni.',
    ],
];
