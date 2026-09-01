<?php
/*
 * Created on   : Tue Sep 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * S3-Backupziel (Feature 123, MVP-726). Endpoint, Bucket, Region und
 * Zugangsdaten liegen SYSTEMWEIT in `backup_target_connections` (Geheimnisse
 * `encrypted`) und werden über die Backupziel-Übersicht gepflegt — nicht in
 * plugin_settings und nicht je Organisation.
 */

return [
    'enabled' => env('S3_BACKUP_ENABLED', true),
    // Ohne ausdrückliche Freigabe nur öffentlich routbare HTTPS-Endpoints
    // (SSRF/DNS-Rebinding). Ein MinIO im eigenen Netz ist der Grund, warum es
    // den Schalter gibt — er gehört bewusst nicht auf „an".
    'allow_private_targets' => env('S3_BACKUP_ALLOW_PRIVATE_TARGETS', false),
];
