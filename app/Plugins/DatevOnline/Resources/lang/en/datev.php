<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : datev.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// DATEV-Online-Plugin (MVP-122), Aufruf mit `datev-online::datev.…`.
return [
    'plugin' => [
        'description' => 'DATEV Online: sign in with DATEV, booking batches via EXTF import instead of download and nightly document images to DATEV Unternehmen online.',
    ],
    'settings' => [
        'client_id' => 'Client ID (DATEV developer portal)',
        'client_id_help' => 'From the app registration at DATEV; register this redirect address there: :url',
        'client_secret' => 'Client secret',
        'sandbox' => 'Use sandbox',
        'sandbox_help' => 'DATEV test environment. Switch off for production only after DATEV approval.',
    ],
    'health' => [
        'not_configured' => 'No DATEV app registration stored.',
        'not_connected' => 'Not signed in with DATEV.',
        'no_client' => 'No client selected.',
        'last_error' => 'Last error: :error',
        'ok' => 'Connected to :client.',
    ],
    'connection_status' => [
        'active' => 'connected',
        'disconnected' => 'not connected',
    ],
    'incoming' => [
        'label' => 'DATEV Unternehmen online',
    ],
    'transfer_kind' => [
        'extf' => 'Booking batch',
        'outgoing_document' => 'Outgoing invoice',
        'incoming_document' => 'Incoming invoice',
    ],
    'transfer_status' => [
        'pending' => 'processing',
        'transferred' => 'transferred',
        'succeeded' => 'imported',
        'failed' => 'failed',
    ],
    'error' => [
        'unknown_client' => 'This client is not released for the signed-in account.',
        'not_ready' => 'Sign in with DATEV and select a client first.',
        'batch_not_exported' => 'Only finalised booking batches can be transferred.',
        'client_mismatch' => 'The batch belongs to a different consultant or client than the connected one.',
        'file_missing' => 'The batch file is missing from storage.',
        'transfer_failed' => 'DATEV did not accept the batch; details are shown in the list.',
    ],
    'flash' => [
        'not_configured' => 'DATEV Online is not set up: client ID and client secret are missing.',
        'state_invalid' => 'Invalid or expired sign-in state — please sign in again.',
        'oauth_denied' => 'Signing in with DATEV was cancelled.',
        'oauth_failed' => 'Token exchange with DATEV failed (:class).',
        'connected' => 'Signed in with DATEV. Please select the client.',
        'disconnected' => 'Disconnected from DATEV.',
        'client_selected' => 'Client :client selected.',
        'documents_saved' => 'Document image settings saved.',
        'uploaded' => ':transferred documents transferred, :failed failed.',
        'batch_transferred' => 'Batch :no handed over to DATEV; DATEV is processing the import.',
        'jobs_refreshed' => ':count imports completed.',
    ],
    'page' => [
        'subtitle' => 'Transfer booking batches and document images directly to DATEV Unternehmen online.',
        'sandbox' => 'Sandbox',
        'connect' => 'Sign in with DATEV',
        'disconnect' => 'Disconnect',
        'disconnect_confirm' => 'Really disconnect from DATEV?',
        'not_configured' => 'The plugin settings are missing the client ID and client secret of the DATEV app registration.',
        'client' => [
            'heading' => 'Client',
            'choose' => 'Client (consultant-client)',
            'save' => 'Apply',
            'error' => 'The client list cannot be retrieved (:class).',
            'none' => 'No clients are released for this sign-in.',
        ],
        'documents' => [
            'heading' => 'Document images',
            'hint' => 'Issued invoices go to DATEV Unternehmen online as “Rechnungsausgang”, received invoices as “Rechnungseingang” once they are assigned in incoming invoices — each once, from the selected date.',
            'enabled' => 'Transfer document images nightly',
            'since' => 'From document date',
            'save' => 'Save',
            'upload' => 'Transfer now',
        ],
        'batches' => [
            'heading' => 'Booking batches',
            'hint' => 'Finalised batches go to DATEV as an EXTF import; consultant and client in the batch must match the connected client.',
            'empty' => 'No finalised booking batches.',
            'transfer' => 'Transfer to DATEV',
            'refresh' => 'Check import status',
            'col' => [
                'batch' => 'Batch',
                'period' => 'Period',
                'client' => 'Consultant-client',
                'status' => 'DATEV',
            ],
        ],
        'transfers' => [
            'heading' => 'Recently transferred documents',
            'empty' => 'No documents transferred yet.',
            'col' => [
                'kind' => 'Type',
                'date' => 'Time',
                'status' => 'Status',
            ],
        ],
    ],
];
