<?php
/*
 * Created on   : Sun Jun 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : permit.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Autorizzazioni',
    'subtitle' => 'Autorizzazioni amministrative per gli eventi – stato, scadenze e documenti giustificativi.',
    'label' => 'Autorizzazione',
    'create' => 'Aggiungi autorizzazione',
    'edit' => 'Modifica autorizzazione',
    'delete_confirm' => 'Eliminare davvero questa autorizzazione?',

    'sections' => [
        'base' => 'Dati',
        'dates' => 'Scadenze',
    ],

    'fields' => [
        'title' => 'Denominazione',
        'status' => 'Stato',
        'event' => 'Evento',
        'event_none' => '— nessuno —',
        'permit_type' => 'Tipo di autorizzazione',
        'authority' => 'Autorità',
        'reference_no' => 'Numero di protocollo',
        'applied_at' => 'Richiesta il',
        'valid_from' => 'Valida dal',
        'valid_until' => 'Valida fino al / scadenza',
        'notes' => 'Note',
        'evidence' => 'Documento giustificativo',
    ],

    'filter' => [
        'all_status' => 'Tutti gli stati',
    ],

    'status' => [
        'required' => 'Necessaria',
        'applied' => 'Richiesta',
        'granted' => 'Concessa',
        'rejected' => 'Respinta',
        'expired' => 'Scaduta',
    ],

    'messages' => [
        'created' => 'Autorizzazione creata.',
        'updated' => 'Autorizzazione aggiornata.',
        'deleted' => 'Autorizzazione eliminata.',
    ],

    'evidence' => [
        'upload' => 'Carica documento',
        'replace' => 'Sostituisci documento',
        'replace_hint' => 'Un nuovo caricamento sostituisce il documento esistente.',
        'hint' => 'Consentito: PDF, JPG, PNG, DOCX (max. 25 MB).',
        'remove' => 'Rimuovi documento',
        'remove_confirm' => 'Rimuovere davvero il documento giustificativo?',
        'too_large' => 'Il file è troppo grande (max. 25 MB).',
        'invalid_type' => 'Tipo di file non consentito (PDF, JPG, PNG, DOCX).',
    ],
];
