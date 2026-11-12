<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : diagnostics.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Diagnose-Seite: Überwachungszugang (MVP-1618).
return [
    'token' => [
        'heading' => 'Accesso di monitoraggio',
        'intro' => 'Un monitoraggio esterno recupera il rapporto senza accesso all’indirizzo :url e invia il token nell’intestazione «Authorization: Bearer …». Il rapporto non contiene nomi né indirizzi.',
        'create' => 'Crea accesso',
        'name' => 'Denominazione',
        'name_help' => 'Ad esempio il nome del sistema di monitoraggio, così potrà riconoscere l’accesso in seguito.',
        'hint' => 'Identificativo',
        'created_by' => 'Creato',
        'last_used' => 'Ultima richiesta',
        'status' => 'Stato',
        'active' => 'Attivo',
        'revoked_state' => 'Revocato',
        'revoke' => 'Revoca',
        'revoke_confirm' => 'Revocare questo accesso? Il monitoraggio non riceverà più il rapporto.',
        'shown_once' => 'Copi ora il token. Viene mostrato solo questa volta e non può essere recuperato.',
        'empty' => 'Nessun accesso di monitoraggio ancora creato.',
        'created' => 'Accesso di monitoraggio creato.',
        'revoked' => 'Accesso di monitoraggio revocato.',
    ],
];
