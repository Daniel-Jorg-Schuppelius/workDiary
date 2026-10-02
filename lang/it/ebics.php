<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ebics.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// EBICS-Bankzugang (MVP-124).
return [
    'title' => 'Accesso bancario EBICS',
    'section' => [
        'access' => 'Dati di accesso della banca',
        'steps' => 'Configurazione',
        'journal' => 'Cronologia',
    ],
    'field' => [
        'host_url' => 'URL EBICS della banca',
        'ebics_host' => 'ID host',
        'ebics_partner' => 'ID cliente (ID partner)',
        'ebics_user' => 'ID partecipante (ID utente)',
    ],
    'hint' => [
        'host_url' => 'Indicata insieme agli ID host, cliente e partecipante nella lettera di accesso EBICS della banca (EBICS 3.0).',
        'active' => 'L’esecuzione notturna recupera gli estratti; recuperati fino al :date.',
    ],
    'step' => [
        'keys' => 'Generare le chiavi (firma, autenticazione, cifratura).',
        'initialize' => 'Inviare le chiavi pubbliche alla banca (INI e HIA).',
        'letter' => 'Stampare la lettera di inizializzazione, firmarla e inviarla alla banca.',
        'activate' => 'Dopo l’attivazione da parte della banca: recuperare le chiavi della banca.',
    ],
    'action' => [
        'save' => 'Salva',
        'keys' => 'Genera chiavi',
        'initialize' => 'Invia alla banca',
        'letter' => 'Scarica lettera',
        'activate' => 'Recupera chiavi della banca',
        'fetch' => 'Recupera estratti ora',
        'suspend' => 'Blocca accesso',
        'submit' => 'Invia tramite EBICS',
    ],
    'confirm' => [
        'suspend' => 'Bloccare l’accesso presso la banca? Poi serviranno nuove chiavi e una nuova lettera.',
        'submit' => 'Inviare ora questa distinta di pagamento alla banca tramite EBICS? L’autorizzazione la dà poi presso la banca.',
    ],
    'last_error' => 'Ultimo errore: :error',
    'flash' => [
        'saved' => 'Dati di accesso salvati.',
        'keys_created' => 'Chiavi generate.',
        'initialized' => 'Chiavi inviate alla banca. Firmi e consegni la lettera di inizializzazione.',
        'activated' => 'Chiavi della banca recuperate: l’accesso è attivato.',
        'suspended' => 'Accesso bloccato.',
        'fetched' => ':statements estratti importati, :skipped già presenti.',
        'submitted' => 'Distinta inviata (ordine :order). La autorizzi presso la banca.',
    ],
    'error' => [
        'host_not_allowed' => 'Questo indirizzo non è consentito come accesso bancario.',
        'locked_after_keys' => 'Dopo aver generato le chiavi i dati di accesso non si possono più modificare: prima blocchi l’accesso.',
        'invalid_step' => 'Questo passo non corrisponde allo stato della configurazione.',
        'not_initialized' => 'La lettera è disponibile solo dopo l’invio delle chiavi alla banca.',
        'not_active' => 'L’accesso EBICS non è attivato.',
        'no_keys' => 'Non ci sono chiavi per questo accesso.',
        'no_data' => 'La banca non ha nuovi dati disponibili.',
        'bank_rejected' => 'La banca ha respinto l’ordine.',
        'failed' => 'La connessione alla banca non è riuscita.',
        'already_submitted' => 'Questa distinta è già stata inviata tramite EBICS.',
    ],
    'letter' => [
        'title' => 'Lettera di inizializzazione EBICS (INI/HIA)',
        'sent_at' => 'Inviata il',
        'key' => [
            'A' => 'Chiave bancaria (firma)',
            'X' => 'Chiave di autenticazione',
            'E' => 'Chiave di cifratura',
        ],
        'hash' => 'Valore hash (SHA-256):',
        'certificate' => 'Certificato emesso il :date',
        'confirmation' => 'Con la presente confermo che le chiavi sopra indicate sono state trasmesse alla banca.',
        'place_date' => 'Luogo, data',
        'signature' => 'Firma del partecipante',
        'printed_at' => 'Creata il :date',
    ],
    'run' => [
        'submitted' => 'Inviata tramite EBICS il :date (ordine :order).',
    ],
];
