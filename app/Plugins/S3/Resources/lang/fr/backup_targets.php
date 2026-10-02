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
        'connect_title' => 'Connecter une cible de sauvegarde S3',
        'connect_legend' => 'Stockage objet compatible S3',
        'connect_submit' => 'Connecter et vérifier',
        'selftest_hint' => 'Avant l enregistrement, un fichier de test est écrit, relu puis supprimé. En cas d échec, la cible n est pas activée.',
        'field' => [
            'name' => 'Désignation',
            'endpoint' => 'Point de terminaison (vide pour AWS S3)',
            'endpoint_help' => 'Indiquez l adresse HTTPS pour MinIO, Wasabi, Hetzner ou Scaleway. Laissé vide, AWS S3 est déduit de la région.',
            'region' => 'Région',
            'region_help' => 'Souvent arbitraire sur un stockage auto-hébergé — us-east-1 est la valeur usuelle.',
            'bucket' => 'Bucket',
            'access_key' => 'Clé d accès',
            'secret_key' => 'Clé secrète',
            'secret_key_help' => 'Enregistrée chiffrée et jamais réaffichée.',
            'prefix' => 'Préfixe (facultatif)',
            'prefix_help' => 'Sous-dossier dans le bucket. WorkDiary y crée son propre dossier pseudonyme.',
            'path_style' => 'Adresser le bucket dans le chemin (path style)',
            'path_style_help' => 'Nécessaire pour MinIO et la plupart des stockages auto-hébergés. AWS S3 n en a pas besoin.',
        ],
        'validation' => [
            'https_required' => 'Le point de terminaison doit commencer par https://.',
            'unsafe_url' => 'Ce point de terminaison pointe vers un réseau privé. Autorisation via S3_BACKUP_ALLOW_PRIVATE_TARGETS.',
        ],
        'flash' => [
            'selftest_failed' => 'La cible a échoué au test d écriture/lecture (:class). Elle n a pas été activée.',
        ],
    ],
];
