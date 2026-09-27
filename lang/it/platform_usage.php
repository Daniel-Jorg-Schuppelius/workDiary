<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : platform_usage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Nutzung je Mandant und Branchenvergleich (MVP-951/949).
return [
    'title' => 'Utilizzo per cliente',
    'subtitle' => 'Utenti, spazio, moduli e ultima attività per organizzazione — solo per la gestione della piattaforma.',
    'back' => 'Organizzazioni',
    'empty' => 'Nessuna organizzazione.',
    'field' => [
        'organization' => 'Organizzazione',
        'status' => 'Stato',
        'users' => 'Utenti',
        'active_users' => 'Attivi (30 giorni)',
        'storage' => 'Spazio',
        'modules' => 'Moduli',
        'last_activity' => 'Ultima attività',
    ],
    'benchmark' => [
        'link' => 'Confronto settoriale',
        'subtitle' => 'Emissioni annue per profilo settoriale principale su tutti i clienti esclusi i demo, solo con almeno tre organizzazioni per settore.',
        'title' => 'Emissioni per settore :year (anonimo)',
        'branch' => 'Settore',
        'organizations' => 'Organizzazioni',
        'mean' => 'Media',
        'median' => 'Mediana',
        'empty' => 'Nessun settore con almeno :min organizzazioni ed emissioni registrate.',
    ],
];
