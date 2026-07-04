<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : integration.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'webhook' => [
        'title' => [
            'index' => 'Webhook',
            'subtitle' => 'Notifiche di eventi in uscita verso sistemi esterni.',
            'help' => 'Come funzionano i webhook?',
            'help_text' => 'Un webhook invia un payload JSON firmato tramite POST HTTPS al tuo URL quando si verifica un evento sottoscritto. La firma (HMAC-SHA256 su timestamp e corpo) è nell’intestazione X-WorkDiary-Signature; verificala con la chiave di firma. Dopo diversi tentativi falliti l’endpoint viene disattivato automaticamente.',
            'create' => 'Crea webhook',
            'edit' => 'Modifica webhook',
            'empty' => 'Nessun webhook creato finora.',
        ],
        'field' => [
            'basics' => 'Dati di base',
            'label' => 'Etichetta',
            'label_placeholder' => 'es. integrazione ERP',
            'url' => 'URL di destinazione',
            'url_help' => 'Endpoint HTTPS che riceve la richiesta POST.',
            'events' => 'Eventi sottoscritti',
            'events_help' => 'Solo gli eventi selezionati attivano un invio.',
            'security' => 'Sicurezza e stato',
            'signing_secret' => 'Chiave di firma',
            'endpoint_active' => 'Endpoint attivo',
            'status' => 'Stato',
            'active' => 'Attivo',
            'inactive' => 'Inattivo',
            'auto_disabled' => 'Disattivato automaticamente',
            'auto_disabled_help' => 'Disattivato automaticamente dopo troppi tentativi falliti. Salvandolo come attivo l’endpoint viene riattivato.',
            'last_deliveries' => 'Ultime consegne',
            'no_deliveries' => 'Ancora nessuna consegna.',
        ],
        'action' => [
            'create' => 'Crea',
            'edit' => 'Modifica',
            'save' => 'Salva',
            'delete' => 'Elimina',
            'delete_confirm' => 'Eliminare davvero questo webhook? I registri di consegna esistenti vengono mantenuti.',
            'rotate_secret' => 'Ruota la chiave di firma',
            'test' => 'Invia evento di test',
        ],
        'secret' => [
            'shown_once' => 'Chiave di firma – visibile solo ora',
            'shown_once_help' => 'Copia la chiave ora. Per motivi di sicurezza non verrà più mostrata in chiaro.',
            'rotate_help' => 'La chiave in chiaro viene mostrata una sola volta alla creazione/rotazione.',
            'rotate_confirm' => 'Generare una nuova chiave di firma? La vecchia chiave diventa subito non valida.',
        ],
        'flash' => [
            'created' => 'Webhook creato.',
            'updated' => 'Webhook aggiornato.',
            'deleted' => 'Webhook eliminato.',
            'secret_rotated' => 'Chiave di firma ruotata.',
            'test_sent' => 'Evento di test messo in coda.',
        ],
        'event' => [
            'openIssue.assigned' => 'Punto aperto assegnato',
            'openIssue.overdue' => 'Punto aperto scaduto',
            'safetyEvent.reported' => 'Evento di sicurezza segnalato',
            'isms.incidentCritical' => 'Incidente di sicurezza ISMS critico',
            'timeCorrection.requested' => 'Correzione dell’orario di lavoro richiesta',
            'monthClosure.submitted' => 'Chiusura mensile inviata',
            'sla.breached' => 'Scadenza SLA violata',
            'document.expired' => 'Documento scaduto',
        ],
        'delivery_status' => [
            'pending' => 'In sospeso',
            'success' => 'Riuscito',
            'failed' => 'Fallito',
        ],
    ],
    'external_type' => [
        'client' => 'Cliente',
        'client_id' => 'ID cliente',
        'contact' => 'Contatto',
        'delivery_note' => 'Bolla di consegna',
        'dunning' => 'Sollecito',
        'entry' => 'Voce',
        'foreign_client' => 'Cliente esterno',
        'invoice' => 'Fattura',
        'order_confirmation' => 'Conferma d\'ordine',
        'project' => 'Progetto',
        'project_id' => 'ID progetto',
        'pushed_entry' => 'Voce trasferita',
        'quotation' => 'Preventivo',
        'session' => 'Sessione',
        'user' => 'Utente',
        'voucher' => 'Documento',
        'work_package' => 'Pacchetto di lavoro',
        'anydesk_id' => 'ID AnyDesk',
        'teamviewer_id' => 'ID TeamViewer',
    ],
    'outbox' => [
        'status' => [
            'pending' => 'In attesa',
            'processing' => 'In trasferimento',
            'confirmed' => 'Confermato',
            'failed' => 'Non riuscito',
            'compensation_required' => 'Compensazione necessaria',
        ],
    ],
];
