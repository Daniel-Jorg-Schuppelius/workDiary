<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : allocation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Ripartire il tempo',
    'entry_duration' => 'Durata della registrazione',
    'hint' => 'Le righe vuote vengono ignorate; svuotare tutte le righe rimuove la ripartizione. La somma delle quote non può superare la durata.',
    'target' => 'Destinazione',
    'minutes' => 'Minuti',
    'quantity' => 'Quantità',
    'comment' => 'Commento',
    'none_option' => '— nessuna quota —',
    'type' => [
        'task' => 'Attività (task)',
        'asset' => 'Asset',
        'project' => 'Progetti',
        'cost_center' => 'Centri di costo',
        'site' => 'Sedi',
        'vehicle' => 'Veicoli',
        'activity_category' => 'Attività',
    ],
    'action' => [
        'split' => 'Ripartisci',
        'save' => 'Salva ripartizione',
    ],
    'flash' => [
        'saved' => 'Ripartizione salvata.',
    ],
    'error' => [
        'locked' => 'La registrazione è bloccata (:reason) — ripartizione non possibile.',
        'invalid_target' => 'Destinazione di ripartizione non valida o estranea.',
        'minutes_min' => 'Ogni quota richiede almeno un minuto.',
        'sum_exceeds' => 'La somma delle quote (:sum min) supera la durata della registrazione (:max min).',
    ],
    // Dimensioni libere del mandante (MVP-514 P2)
    'dimensions' => [
        'nav' => 'Dimensioni temporali',
        'title' => 'Dimensioni temporali libere',
        'intro' => 'Dimensioni personalizzate per la ripartizione del tempo (es. ordini ERP) — solo per destinazioni senza un modello WorkDiary esistente. L\'ID esterno àncora una futura sincronizzazione con provider.',
        'new_type' => 'Nuovo tipo di dimensione',
        'code' => 'Codice',
        'name' => 'Nome',
        'create_type' => 'Crea tipo',
        'enabled' => 'Attivo',
        'disabled' => 'Inattivo',
        'no_types' => 'Ancora nessun tipo di dimensione.',
        'no_values' => 'Ancora nessun valore.',
        'external_id' => 'ID esterno',
        'validity' => 'Validità',
        'valid_from' => 'Valido dal',
        'valid_until' => 'Valido fino al',
        'create_value' => 'Crea valore',
        'delete_value' => 'Elimina',
        'flash' => [
            'type_created' => 'Tipo di dimensione creato.',
            'type_enabled' => 'Tipo di dimensione attivato.',
            'type_disabled' => 'Tipo di dimensione disattivato.',
            'value_created' => 'Valore creato.',
            'value_deleted' => 'Valore eliminato.',
        ],
    ],
];
