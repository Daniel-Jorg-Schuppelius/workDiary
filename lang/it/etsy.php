<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : etsy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Etsy',
    'intro' => 'Collegamento diretto del negozio Etsy (Open API v3): gli ordini arrivano inbox-first nello specchio, l\'assegnazione clienti non è mai cieca. Credenziali della seller app propria dell\'organizzazione: scheda del plugin.',
    'connection' => [
        'active' => 'Collegato: :shop',
        'none' => 'Non collegato',
        'connect' => 'Collega a Etsy',
        'disconnect' => 'Scollega',
        'disconnect_confirm' => 'Scollegare davvero Etsy? Le righe specchio e le assegnazioni restano.',
        'shop_pending' => 'Ricerca negozio in sospeso',
        'shop_conflict' => 'Questo negozio Etsy è già collegato a un\'altra organizzazione.',
        'not_configured' => 'Prima salvare keystring/shared secret nella scheda del plugin.',
    ],
    'setup' => [
        'callback' => 'Redirect URI della seller app (esatta, HTTPS):',
        'webhook' => 'URL webhook per il portale Etsy (eventi order.*):',
    ],
    'field' => [
        'receipt' => 'Ordine',
        'status' => 'Stato',
        'buyer' => 'Acquirente',
        'customer' => 'Cliente',
        'total' => 'Lordo',
        'ordered_at' => 'Ordinato il',
        'shipping' => 'Spedizione',
        'tracking_code' => 'N. tracking',
        'carrier' => 'Corriere',
    ],
    'filter' => [
        'all_statuses' => 'Tutti gli stati',
        'apply' => 'Filtra',
    ],
    'action' => [
        'sync' => 'Sincronizza ora',
        'ship' => 'Segnala spedizione',
        'ship_submit' => 'Segnala',
    ],
    'status' => [
        'open_assignment' => 'Assegnazione aperta',
        'guest' => 'Acquisto ospite',
        'shipped' => 'Spedito',
    ],
    'flash' => [
        'synced' => 'Sincronizzazione Etsy completata: :imported nuovi, :staged assegnazioni aperte.',
        'sync_failed' => 'Sincronizzazione Etsy fallita — dettagli nel log.',
        'already_shipped' => 'L\'ordine è già segnalato come spedito.',
        'ship_queued' => 'Segnalazione di spedizione in coda — Etsy sarà avvisato.',
    ],
    'ledger' => [
        'caption' => 'Commissioni e versamenti degli ultimi 90 giorni (ledger pagamenti Etsy).',
        'type' => 'Tipo',
        'amount' => 'Somma',
        'entries' => 'Voci',
    ],
    'open_inbox' => ':count assegnazioni aperte',
    'last_sync' => 'Ultima sincronizzazione :at',
    'empty' => 'Nessun ordine ancora specchiato.',
];
