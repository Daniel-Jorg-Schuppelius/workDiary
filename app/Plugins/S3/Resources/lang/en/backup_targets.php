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
        'connect_title' => 'Connect S3 backup target',
        'connect_legend' => 'S3-compatible object storage',
        'connect_submit' => 'Connect and verify',
        'selftest_hint' => 'Before saving, a test file is written, read back and deleted. If that fails, the target is not activated.',
        'field' => [
            'name' => 'Name',
            'endpoint' => 'Endpoint (empty for AWS S3)',
            'endpoint_help' => 'Enter the HTTPS address for MinIO, Wasabi, Hetzner or Scaleway. Left empty, AWS S3 is derived from the region.',
            'region' => 'Region',
            'region_help' => 'Often arbitrary on self-hosted storage — us-east-1 is the usual default.',
            'bucket' => 'Bucket',
            'access_key' => 'Access key',
            'secret_key' => 'Secret key',
            'secret_key_help' => 'Stored encrypted and never shown again.',
            'prefix' => 'Prefix (optional)',
            'prefix_help' => 'Subfolder inside the bucket. WorkDiary creates its own pseudonym folder below it.',
            'path_style' => 'Address bucket in the path (path style)',
            'path_style_help' => 'Required for MinIO and most self-hosted storage. AWS S3 does not need it.',
        ],
        'validation' => [
            'https_required' => 'The endpoint must start with https://.',
            'unsafe_url' => 'This endpoint points into a private network. Allow it via S3_BACKUP_ALLOW_PRIVATE_TARGETS.',
        ],
        'flash' => [
            'selftest_failed' => 'The target failed the write/read test (:class). It was not activated.',
        ],
    ],
];
