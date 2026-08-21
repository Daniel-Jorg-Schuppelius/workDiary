<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : guarantee.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Bürgschaftsregister (Feature 114, MVP-603).
return [
    'title' => 'Fideiussioni',
    'subtitle' => 'Fideiussioni prestate e ricevute con scadenza e prova di restituzione',
    'empty' => 'Nessuna fideiussione registrata.',
    'unlimited' => 'senza scadenza',
    'created' => 'Fideiussione registrata.',
    'updated' => 'Fideiussione aggiornata.',
    'returned' => 'Restituzione registrata.',
    'drawn' => 'Escussione registrata.',
    'secured' => 'Ritenuta a garanzia sostituita dalla fideiussione.',
    'not_active' => 'Questa fideiussione non è più attiva.',
    'retention_not_open' => 'Questa ritenuta non è più aperta.',
    'foreign_organization' => 'Fideiussione e ritenuta appartengono a organizzazioni diverse.',
    'amount_too_low' => 'La fideiussione non copre la ritenuta — una fideiussione minore non la sostituisce.',
    'issuer_hint' => 'Banca o assicuratore secondo il documento; in alternativa scegliere un fornitore.',
    'issuer_supplier' => 'Garante dai dati anagrafici',
    'action' => [
        'create' => 'Registra fideiussione',
        'edit' => 'Modifica fideiussione',
        'returned' => 'Documento restituito',
    ],
    'kpi' => [
        'issued' => 'Prestate (attive)',
        'issued_hint' => 'Finché non torna, la commissione di aval continua.',
        'received' => 'Ricevute (attive)',
        'received_hint' => 'Se scade inosservata, la garanzia è persa.',
        'expiring' => 'In scadenza entro 90 giorni',
        'return_due' => 'Restituzione dovuta',
        'return_due_hint' => 'La ritenuta sostituita è liberata — il documento va restituito.',
    ],
    'column' => [
        'reference' => 'N. fideiussione',
        'direction' => 'Direzione',
        'kind' => 'Tipo',
        'issuer' => 'Garante',
        'party' => 'Controparte',
        'amount' => 'Importo',
        'issued_on' => 'Emessa il',
        'expires_on' => 'Scadenza',
        'status' => 'Stato',
        'customer' => 'Cliente',
        'supplier' => 'Fornitore',
        'project' => 'Progetto',
        'responsible' => 'Responsabile',
        'note' => 'Nota',
    ],
    'filter' => [
        'direction' => 'Direzione',
        'status' => 'Stato',
    ],
];
