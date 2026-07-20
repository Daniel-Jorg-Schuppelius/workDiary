<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : settingsregistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Impostazioni (registro)',
        'subtitle' => 'Impostazioni di sistema e organizzazione registrate — con valore effettivo, origine e ripristino.',
        'help_text' => 'Solo le chiavi dichiarate nel registro sono modificabili qui; validazione, sensibilità e audit sono definiti per chiave. I valori infrastrutturali (APP_KEY, database, trasporto mail) volutamente non compaiono qui.',
    ],
    'scopes' => [
        'system' => 'Sistema (gestore)',
        'organization' => 'Organizzazione',
        'user' => 'Utente',
    ],
    'sources' => [
        'organization' => 'Override org',
        'system' => 'Override di sistema',
        'config' => 'File di configurazione',
        'default' => 'Valore predefinito',
    ],
    'field' => [
        'search' => 'Cerca chiavi…',
        'sensitive' => 'Sensibile',
        'sensitive_placeholder' => 'Inserire un nuovo valore (valore attuale nascosto)',
        'affects' => 'Riguarda',
    ],
    'action' => [
        'save' => 'Salva',
        'reset' => 'Ripristina predefinito',
        'history' => 'Cronologia',
        'export' => 'Esporta (JSON)',
    ],
    'empty' => [
        'title' => 'Nessuna impostazione trovata',
        'message' => 'Nessuna chiave di registro per questo ambito o termine di ricerca.',
        'history' => 'Nessuna modifica registrata finora.',
    ],
    'flash' => [
        'saved' => 'Impostazione :key salvata.',
        'reset' => 'Impostazione :key ripristinata al valore predefinito.',
    ],
];
