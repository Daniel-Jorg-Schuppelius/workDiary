<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sustainability.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Nachhaltigkeit: Standorte, Benchmarking, Auszug (MVP-929/930).
return [
    'site' => [
        'benchmark' => 'Confronto tra sedi',
        'subtitle' => 'Emissioni per sede e anno dai dati di attività, con intensità per m² e per addetto. Le attività senza fattore non sono conteggiate.',
        'back' => 'Sostenibilità',
        'create' => 'Aggiungi sede',
        'edit' => 'Modifica',
        'save' => 'Salva',
        'inactive' => 'inattiva',
        'empty' => 'Nessuna sede finora — aggiunga sedi e registri attività con la sede.',
        'field' => [
            'site' => 'Sede',
            'code' => 'Codice',
            'year' => 'Anno',
            'area_m2' => 'Superficie (m²)',
            'headcount' => 'Addetti',
            'co2e_t' => 'CO₂e (t)',
            'per_m2' => 'kg CO₂e per m²',
            'per_head' => 'kg CO₂e per persona',
            'missing' => 'senza fattore',
            'active' => 'attiva',
        ],
        'flash' => [
            'saved' => 'Sede salvata.',
        ],
    ],
    'excerpt' => [
        'title' => 'Estratto pubblico',
        'intro' => 'Pubblichi uno snapshot del report congelato. Viene mostrato tramite un link e come avviso nel portale clienti — senza dichiarazioni di conformità o neutralità climatica.',
        'snapshot' => 'Snapshot pubblicato',
        'none' => '— non pubblicato —',
        'with_targets' => 'Includere gli obiettivi',
        'publish' => 'Salva pubblicazione',
        'token_once' => 'Link visibile solo ora — lo copi.',
        'link' => 'Link pubblico',
        'state_none' => 'non emesso',
        'state_active' => 'attivo',
        'state_paused' => 'sospeso',
        'pause' => 'Sospendi',
        'resume' => 'Riprendi',
        'revoke' => 'Revoca',
        'rotate' => 'Emetti nuovo link',
        'issue' => 'Emetti link',
        'public_title' => 'Estratto di sostenibilità :org',
        'period' => 'Periodo :from – :to',
        'emissions' => 'Emissioni di gas serra',
        'scope' => 'Scope :scope',
        'targets' => 'Obiettivi',
        'disclaimer' => 'Dati congelati dalle attività registrate; nessuna dichiarazione di conformità o neutralità climatica.',
        'factors' => 'Set di fattori: :sets.',
        'portal_subject' => 'Estratto di sostenibilità :from – :to',
        'portal_body' => 'Emissioni di gas serra nel periodo: :tonnes t CO₂e.',
        'flash' => [
            'published' => 'Pubblicazione salvata.',
            'issued' => 'Link emesso.',
            'revoked' => 'Link revocato.',
            'saved' => 'Impostazione salvata.',
        ],
    ],
    // Vergleich nach Kundengruppe (MVP-949).
    'customer_group' => [
        'title' => 'Emissioni per gruppo di clienti :year',
        'group' => 'Gruppo di clienti',
        'customers' => 'Clienti',
        'per_customer' => 'per cliente',
        'none' => 'Senza gruppo di clienti',
        'customer' => 'Cliente (per il confronto per gruppo di clienti)',
        'empty' => 'Nessuna attività collegata a clienti quest’anno.',
    ],
];
