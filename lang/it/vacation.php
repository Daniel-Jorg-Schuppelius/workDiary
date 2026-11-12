<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : vacation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Urlaubsantrag zur Korrektur zurückgeben (MVP-1554).
    'revision' => [
        'title' => 'Richiedi correzione',
        'action' => 'Richiedi correzione',
        'legend' => 'Indicazione per la correzione',
        'note' => 'Che cosa va modificato?',
        'note_hint' => 'La persona richiedente vede l’indicazione, modifica la richiesta e la presenta di nuovo.',
        'requested' => 'Richiesta di ferie restituita per correzione.',
        'resubmitted' => 'Richiesta di ferie modificata e presentata di nuovo.',
        'returned' => 'Questa richiesta è stata restituita per correzione.',
        'resubmit_hint' => 'Salvando presenta di nuovo la richiesta.',
        'not_decidable' => 'La richiesta è in correzione presso la persona richiedente e può essere decisa solo dopo la nuova presentazione.',
    ],
];
