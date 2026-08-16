<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : b2b_catalog.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Accesso catalogo B2B (funzionalità 099): punchout OCI in uscita + ricezione ordini openTRANS.
return [
    'title' => 'Accesso catalogo B2B',
    'intro' => 'I sistemi d\'acquisto dei vostri clienti B2B accedono via OCI 4.0 al catalogo articoli rilasciato e restituiscono gli ordini come openTRANS 2.1 ORDER.',
    'punchout_url' => 'URL punchout (per il sistema d\'acquisto del cliente)',

    'access_new_heading' => 'Emetti nuovo accesso',
    'access_new_hint' => 'Un accesso per cliente: nome utente + secret per il punchout OCI. Il secret viene mostrato una sola volta.',
    'access_heading' => 'Accessi punchout',
    'access_empty' => 'Nessun accesso emesso finora.',
    'access_title' => 'Accesso: :label',

    'new_secret_heading' => 'Nuovo secret punchout',
    'new_secret_hint' => 'Copialo ora e registralo nel sistema d\'acquisto del cliente — il testo in chiaro viene mostrato solo questa volta.',

    'items_heading' => 'Articoli rilasciati',
    'items_hint' => 'Solo gli articoli esplicitamente rilasciati sono visibili nel punchout. Senza prezzo cliente vale il prezzo di vendita standard.',
    'items_empty' => 'Nessun articolo rilasciato finora.',

    'orders_heading' => 'Ordini openTRANS',
    'orders_hint' => 'Gli ordini (caricamento, e-mail o cloud) appaiono come proposte nella inbox di assegnazione; solo la registrazione crea l\'incarico.',
    'orders_empty' => 'Nessun ordine ricevuto finora.',

    'field' => [
        'customer' => 'Cliente',
        'customer_placeholder' => '… seleziona cliente',
        'label' => 'Etichetta',
        'username' => 'Nome utente',
        'items_count' => 'Articoli',
        'last_used' => 'Ultimo utilizzo',
        'status' => 'Stato',
        'actions' => 'Azioni',
        'article' => 'Articolo',
        'article_placeholder' => '… seleziona articolo',
        'article_number' => 'N. articolo',
        'article_name' => 'Articolo',
        'default_price' => 'Prezzo standard',
        'custom_price' => 'Prezzo cliente',
        'custom_price_placeholder' => 'Standard',
        'order_id' => 'N. ordine',
        'source' => 'Canale',
        'total_net' => 'Totale netto',
        'ordered_at' => 'Data ordine',
    ],

    'action' => [
        'datanorm' => 'Esporta DATPREIS',
        'issue' => 'Emetti accesso',
        'manage' => 'Gestisci',
        'revoke' => 'Disattiva',
        'rotate' => 'Ruota secret',
        'back' => 'Torna alla panoramica',
        'release' => 'Rilascia articolo',
        'remove' => 'Rimuovi',
        'upload_order' => 'Carica ordine',
    ],

    'status' => [
        'active' => 'Attivo',
        'revoked' => 'Disattivato',
        'order_open' => 'Aperto (inbox)',
        'order_booked' => 'Registrato',
        'order_dismissed' => 'Scartato',
    ],

    'flash' => [
        'datanorm_empty' => 'Nessun articolo autorizzato con prezzo per questo accesso.',
        'datanorm_revoked' => 'Questo accesso è revocato — le liste prezzi del cliente non vengono più esportate.',
        'access_issued' => 'Accesso emesso.',
        'access_rotated' => 'Secret ruotato.',
        'access_revoked' => 'Accesso disattivato.',
        'item_released' => 'Articolo rilasciato.',
        'item_removed' => 'Rilascio rimosso.',
        'order_received' => 'Ordine :id ricevuto — una proposta è in attesa nella inbox di assegnazione.',
        'order_duplicate' => 'L\'ordine :id è già registrato (nessuna modifica).',
    ],

    'error' => [
        'not_opentrans' => 'Il file non è un openTRANS 2.1 ORDER leggibile: :reason',
        'customer_required' => 'Seleziona un cliente.',
        'not_open' => 'L\'ordine non è più aperto.',
    ],

    'order' => [
        'entry_title' => 'Ordine :id',
        'entry_intro' => 'Ordine openTRANS :id (canale: :source).',
        'line_unmatched' => 'articolo non abbinato',
    ],

    'public' => [
        'title' => 'Catalogo B2B',
        'footer' => 'Catalogo punchout — il carrello viene consegnato al vostro sistema d\'acquisto; l\'ordine passa dal vostro sistema.',
        'search_placeholder' => 'Numero articolo o denominazione …',
        'search' => 'Cerca',
        'empty' => 'Nessun articolo rilasciato trovato.',
        'col_number' => 'N. articolo',
        'col_name' => 'Denominazione',
        'col_unit' => 'Unità',
        'col_price' => 'Prezzo',
        'col_quantity' => 'Quantità',
        'page_of' => 'Pagina :current di :last',
        'prev' => 'Indietro',
        'next' => 'Avanti',
        'to_cart' => 'Trasferisci carrello',
        'transfer_title' => 'Consegna al sistema d\'acquisto',
        'transfer_hint' => 'Il carrello viene trasferito al vostro sistema d\'acquisto. Se il reindirizzamento non parte automaticamente, usa il pulsante.',
        'transfer_submit' => 'Trasferisci ora il carrello',
        'error_title' => 'Accesso catalogo',
        'error_hook_url' => 'HOOK_URL non valida — sono ammessi solo indirizzi HTTPS.',
        'error_credentials' => 'Credenziali non valide o accesso disattivato.',
        'error_session' => 'La sessione catalogo è scaduta. Riavvia il punchout dal tuo sistema d\'acquisto.',
        'error_empty_cart' => 'Nessuna posizione con quantità selezionata.',
    ],
];
