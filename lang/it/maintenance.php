<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : maintenance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'window' => [
        'title' => 'Finestre di manutenzione',
        'subtitle' => 'Annunciare, avviare, prolungare e chiudere in modo tracciabile i fermi pianificati.',
        'read_only_message' => 'Manutenzione: l\'applicazione è temporaneamente in sola lettura.',
        'scope' => [
            'system' => 'A livello di installazione',
            'organization' => 'Solo questa organizzazione',
        ],
        'mode' => [
            'full' => 'Blocco completo',
            'read_only' => 'Sola lettura',
            'block_ingest' => 'Ingest bloccato',
            'read_only_toggle' => 'Modalità sola lettura (la consultazione resta possibile)',
            'block_ingest_toggle' => 'Bloccare ingest terminale/CTI/posizione durante la manutenzione',
        ],
        'status' => [
            'planned' => 'Pianificata',
            'announced' => 'Annunciata',
            'active' => 'Attiva',
            'extended' => 'Prolungata',
            'completed' => 'Completata',
            'rolled_back' => 'Rollback',
            'cancelled' => 'Annullata',
        ],
        'field' => [
            'window' => 'Finestra temporale',
            'scope' => 'Ambito',
            'mode' => 'Modalità',
            'status' => 'Stato',
            'actions' => 'Azioni',
            'announce_from' => 'Annuncio dal',
            'starts_at' => 'Inizio',
            'ends_at' => 'Fine',
            'message' => 'Testo informativo',
        ],
        'action' => [
            'plan' => 'Pianifica finestra di manutenzione',
            'save' => 'Pianifica',
            'announce' => 'Annuncia',
            'start' => 'Avvia ora',
            'complete' => 'Termina',
            'extend' => 'Prolunga',
            'rollback' => 'Rollback',
            'cancel' => 'Annulla',
        ],
        'banner' => [
            'upcoming' => 'Manutenzione pianificata: :from – :to — salvare il lavoro per tempo.',
            'read_only' => 'Manutenzione attiva fino alle :to — le modifiche sono temporaneamente impossibili.',
        ],
        'hint' => [
            'message' => 'Facoltativo: cosa viene manutenuto, cosa aspettarsi?',
        ],
        'empty' => [
            'title' => 'Nessuna finestra di manutenzione',
            'message' => 'Non sono pianificate finestre di manutenzione.',
        ],
        'flash' => [
            'planned' => 'Finestra di manutenzione pianificata.',
            'announce' => 'Finestra di manutenzione annunciata.',
            'start' => 'Finestra di manutenzione avviata.',
            'complete' => 'Finestra di manutenzione completata.',
            'extend' => 'Finestra di manutenzione prolungata.',
            'rollback' => 'Manutenzione chiusa come rollback.',
            'cancel' => 'Finestra di manutenzione annullata.',
        ],
    ],
];
