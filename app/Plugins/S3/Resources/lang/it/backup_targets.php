<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : backup_targets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    's3' => [
        'connect_title' => 'Collega destinazione di backup S3',
        'connect_legend' => 'Archiviazione a oggetti compatibile S3',
        'connect_submit' => 'Collega e verifica',
        'selftest_hint' => 'Prima del salvataggio viene scritto, riletto ed eliminato un file di prova. Se fallisce, la destinazione non viene attivata.',
        'field' => [
            'name' => 'Denominazione',
            'endpoint' => 'Endpoint (vuoto per AWS S3)',
            'endpoint_help' => 'Inserire l indirizzo HTTPS per MinIO, Wasabi, Hetzner o Scaleway. Se vuoto, AWS S3 viene dedotto dalla regione.',
            'region' => 'Regione',
            'region_help' => 'Spesso arbitraria su archivi self-hosted — us-east-1 è il valore consueto.',
            'bucket' => 'Bucket',
            'access_key' => 'Access key',
            'secret_key' => 'Secret key',
            'secret_key_help' => 'Salvata cifrata e mai più mostrata.',
            'prefix' => 'Prefisso (facoltativo)',
            'prefix_help' => 'Sottocartella nel bucket. WorkDiary vi crea la propria cartella pseudonimo.',
            'path_style' => 'Indirizzare il bucket nel percorso (path style)',
            'path_style_help' => 'Necessario per MinIO e per la maggior parte degli archivi self-hosted. AWS S3 non lo richiede.',
        ],
        'validation' => [
            'https_required' => 'L endpoint deve iniziare con https://.',
            'unsafe_url' => 'Questo endpoint punta a una rete privata. Abilitazione tramite S3_BACKUP_ALLOW_PRIVATE_TARGETS.',
        ],
        'flash' => [
            'selftest_failed' => 'La destinazione non ha superato il test di scrittura/lettura (:class). Non è stata attivata.',
        ],
    ],
];
