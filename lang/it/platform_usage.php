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
    // Nutzungsabrechnung, Abrechnungsdaten und Tarifanfragen (MVP-956/957).
    'billing' => [
        'title' => 'Fatturazione a consumo',
        'subtitle' => 'Utilizzo mensile per organizzazione, valorizzato con i prezzi unitari delle impostazioni di sistema (platform_billing.*). Non viene creata alcuna fattura.',
        'month' => 'Mese',
        'plan' => 'Piano',
        'amount' => 'Importo',
        'empty' => 'Ancora nessun utilizzo mensile. Viene registrato il primo del mese (platform:usage-snapshot).',
        'note' => 'Importo = canone base + utenti + utenti attivi + GB di spazio iniziati, ciascuno per il prezzo unitario.',
    ],
    'plan' => [
        'free' => 'Free',
        'pro' => 'Pro',
        'enterprise' => 'Enterprise',
    ],
    'billing_profile' => [
        'title' => 'Dati di fatturazione',
        'subtitle' => 'Destinatario delle fatture per l’uso del software e cambi di piano.',
        'contact' => 'Destinatario della fattura',
        'save' => 'Salva',
        'invalid_vat' => 'La partita IVA non è valida.',
        'field' => [
            'name' => 'Nome / azienda',
            'email' => 'E-mail per le fatture',
            'street' => 'Via',
            'zip' => 'CAP',
            'city' => 'Città',
            'country' => 'Paese (ISO)',
            'vat_id' => 'Partita IVA',
            'reference' => 'Riferimento d’ordine',
        ],
        'hint' => [
            'reference' => 'Compare sulle fatture del gestore.',
        ],
        'flash' => [
            'saved' => 'Dati di fatturazione salvati.',
        ],
    ],
    'plan_request' => [
        'title' => 'Richiedere un cambio di piano',
        'open_title' => 'Richieste di piano aperte',
        'history' => 'Richieste',
        'current' => 'Piano attuale: :plan',
        'send' => 'Invia richiesta',
        'withdraw' => 'Ritira',
        'done' => 'Evasa',
        'decline' => 'Rifiuta',
        'empty' => 'Nessuna richiesta.',
        'already_open' => 'Esiste già una richiesta aperta.',
        'field' => [
            'plan' => 'Piano desiderato',
            'addons' => 'Moduli aggiuntivi',
            'note' => 'Nota',
            'requester' => 'Richiesto da',
            'created_at' => 'Data',
        ],
        'hint' => [
            'addons' => 'Codici modulo separati da virgole, ad es. module.rental',
        ],
        'status' => [
            'open' => 'Aperta',
            'done' => 'Evasa',
            'declined' => 'Rifiutata',
            'withdrawn' => 'Ritirata',
        ],
        'flash' => [
            'sent' => 'Richiesta inviata. Il gestore La ricontatterà.',
            'withdrawn' => 'Richiesta ritirata.',
            'decided' => 'Richiesta chiusa.',
        ],
    ],
];
