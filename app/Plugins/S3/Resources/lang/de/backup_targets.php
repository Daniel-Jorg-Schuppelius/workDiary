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
        'connect_title' => 'S3-Backupziel verbinden',
        'connect_legend' => 'S3-kompatibler Objektspeicher',
        'connect_submit' => 'Verbinden und prüfen',
        'selftest_hint' => 'Vor dem Speichern wird eine Testdatei geschrieben, gelesen und wieder gelöscht. Schlägt das fehl, wird das Ziel nicht aktiviert.',
        'field' => [
            'name' => 'Bezeichnung',
            'endpoint' => 'Endpoint (leer für AWS S3)',
            'endpoint_help' => 'Für MinIO, Wasabi, Hetzner oder Scaleway die HTTPS-Adresse eintragen. Bleibt das Feld leer, wird AWS S3 aus der Region abgeleitet.',
            'region' => 'Region',
            'region_help' => 'Bei selbstbetriebenen Speichern oft beliebig — us-east-1 ist der übliche Vorgabewert.',
            'bucket' => 'Bucket',
            'access_key' => 'Access Key',
            'secret_key' => 'Secret Key',
            'secret_key_help' => 'Wird verschlüsselt gespeichert und nie wieder angezeigt.',
            'prefix' => 'Präfix (optional)',
            'prefix_help' => 'Unterordner im Bucket. Darunter legt WorkDiary einen eigenen Pseudonym-Ordner an.',
            'path_style' => 'Bucket im Pfad adressieren (Path-Style)',
            'path_style_help' => 'Für MinIO und die meisten selbstbetriebenen Speicher nötig. AWS S3 braucht es nicht.',
        ],
        'validation' => [
            'https_required' => 'Der Endpoint muss mit https:// beginnen.',
            'unsafe_url' => 'Dieser Endpoint zeigt in ein internes Netz. Freigabe über S3_BACKUP_ALLOW_PRIVATE_TARGETS.',
        ],
        'flash' => [
            'selftest_failed' => 'Das Ziel hat den Schreib-/Lesetest nicht bestanden (:class). Es wurde nicht aktiviert.',
        ],
    ],
];
