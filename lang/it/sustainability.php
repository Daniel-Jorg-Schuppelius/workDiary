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
        'statement' => 'Dichiarazione per l’estratto',
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
    // Klimanachweise und Umweltaussagen (MVP-961).
    'offset' => [
        'title' => 'Certificati climatici',
        'subtitle' => 'Compensazioni, garanzie di origine e contributi climatici con standard, quantità e ritiro.',
        'separate' => 'I certificati sono riportati separatamente e mai detratti dalle emissioni.',
        'list' => 'Certificati',
        'public_title' => 'Certificati climatici (non detratti)',
        'add' => 'Registra certificato',
        'empty' => 'Ancora nessun certificato.',
        'totals' => 'Totale per anno',
        'evidenced' => 'documentato',
        'not_evidenced' => 'Manca ritiro o riferimento di registro',
        'confirm_delete' => 'Eliminare il certificato?',
        'kind' => [
            'compensation' => 'Compensazione',
            'green_energy' => 'Garanzia di origine (energia verde)',
            'contribution' => 'Contributo climatico',
        ],
        'field' => [
            'kind' => 'Tipo',
            'provider' => 'Fornitore',
            'standard' => 'Standard',
            'project_name' => 'Progetto',
            'quantity_t' => 'Quantità (t CO₂e)',
            'claim_year' => 'Anno',
            'vintage_year' => 'Annata',
            'retired_on' => 'Ritirato il',
            'registry_reference' => 'Riferimento di registro',
            'note' => 'Nota',
            'evidence' => 'Prova',
        ],
        'hint' => [
            'standard' => 'ad es. Gold Standard, VCS, GO',
        ],
        'flash' => [
            'saved' => 'Certificato registrato.',
            'deleted' => 'Certificato eliminato.',
        ],
    ],
    'claim' => [
        'title' => 'Verificare le dichiarazioni ambientali',
        'intro' => 'Verifica i testi alla ricerca di formulazioni vietate o che richiedono prove secondo la direttiva EmpCo (UE 2024/825, dal 27/09/2026).',
        'text' => 'Testo',
        'check' => 'Verifica',
        'none' => 'Nessuna formulazione critica trovata.',
        'hint' => 'Verificato alla pubblicazione per dichiarazioni ambientali vietate.',
        'warning' => 'Pubblicato, ma verifichi: :terms',
        'disclaimer' => 'Avviso automatico, non una verifica legale.',
        'reason' => [
            'offset_neutrality' => 'Le dichiarazioni di neutralità o impatto climatico basate sulla compensazione sono vietate.',
            'generic' => 'Dichiarazione ambientale generica: consentita solo con prestazioni ambientali eccellenti riconosciute.',
            'evidence' => 'La dichiarazione richiede una prova verificabile.',
        ],
    ],
];
