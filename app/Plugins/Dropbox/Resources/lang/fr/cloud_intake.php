<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : cloud_intake.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'action' => [
        'connect_dropbox' => 'Connecter Dropbox',
    ],
    'dropbox' => [
        'description' => 'Lit les documents des dossiers Dropbox surveillés (entrée de documents cloud) — avec règles de dossiers, justificatif de transfert et boîte de réception pour les cas ambigus.',
        'health' => [
            'not_configured' => 'Clés d\'application Dropbox non configurées.',
            'no_org_context' => 'Pas de contexte d\'organisation (exécution système).',
            'attention' => 'Au moins une connexion Dropbox demande attention (reconnexion/bloquée).',
            'backup_attention' => 'La cible de sauvegarde Dropbox demande attention (reconnexion/bloquée) — concerne toutes les organisations.',
            'ok' => 'Connexions Dropbox en bon état.',
            'error' => 'Vérification d\'état échouée (:class).',
        ],
    ],
];
