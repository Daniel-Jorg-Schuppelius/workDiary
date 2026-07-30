<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : integrity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Segnali secondari di integrità e lockdown (funzionalità 097, MVP-447/448).
return [
    'anchor' => [
        'unavailable' => 'Ancora di integrità esterna non leggibile (destinazione di backup raggiungibile?) — segnale secondario saltato.',
        'root_mismatch' => 'L\'ancora esterna differisce: root dell\'ancora :remote, locale :local.',
        'history_mismatch' => 'La cronologia dei controlli differisce dall\'ancora esterna — la cronologia locale potrebbe essere stata sostituita.',
    ],
    'env' => [
        'missing' => '.env mancante o non leggibile (la baseline contiene un\'impronta).',
        'values_changed' => '.env modificato (stesso set di chiavi, valori diversi).',
        'keys_changed' => '.env modificato (set di chiavi diverso: :before → :after chiavi).',
    ],
    'git' => [
        'head_mismatch' => 'Il HEAD Git :head non corrisponde alla build della baseline :expected (WARN).',
        'dirty' => 'Albero di lavoro Git non pulito nell\'ambito di scansione: :count percorso/i — :paths (WARN).',
    ],
    'lockdown' => [
        'crisis_title' => 'Lockdown di integrità: codice sorgente manomesso',
        'crisis_description' => 'Una baseline di release firmata mostra deviazioni in più controlli consecutivi (:modified modificati, :added nuovi, :deleted eliminati). L\'installazione è in modalità manutenzione.',
    ],
];
