<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : updates.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => ['section' => 'Aggiornamenti disponibili'],
    'field' => [
        'mode' => 'Modalità di verifica',
        'last_checked' => 'Ultima verifica',
        'component' => 'Componente',
        'versions' => 'Installata → Disponibile',
        'classification' => 'Classificazione',
        'requirements' => 'Preparazione',
        'incompatible' => 'Incompatibile con questa versione dell\'app',
        'changelog' => 'Registro modifiche',
    ],
    'classification' => [
        'normal' => 'Routine',
        'recommended' => 'Consigliato',
        'security' => 'Sicurezza',
        'critical' => 'Critico',
    ],
    'requires' => [
        'backup' => 'Backup richiesto',
        'maintenance_window' => 'Finestra di manutenzione consigliata',
        'migrations' => 'Migrazioni del database',
    ],
    'action' => [
        'check_now' => 'Verifica ora',
        'import' => 'Importazione offline',
        'snooze' => 'Posticipa',
        'acknowledge' => 'Silenzia',
    ],
    'empty' => 'Nessun aggiornamento in sospeso noto.',
    'flash' => [
        'checked' => 'Verifica completata — :count aggiornamento/i in sospeso.',
        'imported' => 'Documento importato — :count aggiornamento/i in sospeso.',
        'snoozed' => 'Avviso di aggiornamento posticipato.',
        'acknowledged' => 'Avviso silenziato (resta visibile qui).',
    ],
];
