<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : time-correction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Auftrag einer Zeitbuchung per Korrekturantrag ändern (MVP-1552).
    'order' => [
        'label' => 'Cambiare l’ordine',
        'unchanged' => '— invariato —',
        'none' => '— senza ordine —',
        'current' => 'attuale',
    ],
    // Eigene Stempelung korrigieren, auch offline (MVP-1595).
    'attendance' => [
        'title' => 'Correggere la timbratura',
        'action' => 'Correggere la timbratura',
        'submit' => 'Invia correzione',
        'intro' => 'La correzione segue l’iter di approvazione come richiesta; se la Sua organizzazione consente l’autocorrezione, vale subito. Senza connessione viene salvata sul dispositivo e trasmessa al contatto successivo.',
        'span' => 'Inizio e fine',
        'reason' => 'Motivazione',
        'reason_placeholder' => 'Almeno :min caratteri, ad es. «dimenticato di timbrare l’uscita»',
        'changed' => 'La timbratura è stata modificata nel frattempo: riapra la finestra di dialogo.',
        'applied' => 'Timbratura corretta.',
        'submitted' => 'Correzione inviata per l’approvazione.',
    ],
];
