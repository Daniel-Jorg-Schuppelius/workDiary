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
        'connect_google' => 'Connecter Google Drive',
    ],
    'google' => [
        'description' => 'Lit les documents des dossiers Google Drive surveillés (entrée de documents cloud) — Mon Drive et Drive partagés ; déploiement bloqué jusqu’à la vérification OAuth Google.',
        'health' => [
            'not_configured' => 'Clés client Google Drive non configurées.',
            'no_org_context' => 'Pas de contexte d’organisation (exécution système).',
            'attention' => 'Au moins une connexion Google Drive demande attention (reconnexion/bloquée).',
            'backup_attention' => 'La cible de sauvegarde Google Drive demande attention (reconnexion/bloquée) — concerne toutes les organisations.',
            'ok' => 'Connexions Google Drive en bon état.',
            'error' => 'Vérification d’état échouée (:class).',
        ],
    ],
];
