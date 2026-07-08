<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : webdav.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Stockage WebDAV',
    'intro' => 'Les documents validés sont copiés par type de document dans un stockage WebDAV externe (Nextcloud/ownCloud) — avec preuve de transfert (empreinte, heure, cible). WorkDiary reste la référence ; les modifications externes des fichiers copiés apparaissent comme un conflit, jamais un écrasement silencieux.',

    'conflict' => [
        'subtitle' => 'Modification externe détectée — copie suspendue (pas d\'écrasement).',
        'action' => [
            'overwrite' => 'Écraser le distant',
            'import' => 'Importer comme nouvelle version',
            'detach' => 'Détacher la copie',
        ],
        'confirm' => [
            'overwrite' => 'Écraser le fichier externe avec l\'état local ? La modification externe sera perdue.',
            'import' => 'Importer l\'état externe comme nouvelle version locale ?',
            'detach' => 'Détacher définitivement la copie de ce document ? La connexion reste active.',
        ],
        'flash' => [
            'overwritten' => 'Fichier externe écrasé avec l\'état local.',
            'imported' => 'État externe importé comme nouvelle version locale.',
            'detached' => 'Copie détachée pour ce document.',
            'failed' => 'Échec de la résolution du conflit : :reason',
        ],
        'import_note' => 'Importé depuis WebDAV (résolution de conflit).',
    ],

    'health' => [
        'ok' => 'Connecté',
        'failing' => 'Injoignable',
        'inactive' => 'Inactif',
    ],

    'action' => [
        'mirror' => 'Copier maintenant',
        'disconnect' => 'Déconnecter',
        'save' => 'Enregistrer',
    ],

    'connection' => [
        'heading' => 'Stockage',
    ],

    'field' => [
        'name' => 'Libellé',
        'base_url' => 'URL de la collection',
        'base_url_help' => 'Dossier WebDAV complet, p. ex. .../remote.php/dav/files/UTILISATEUR/WorkDiary.',
        'username' => 'Nom d\'utilisateur',
        'app_password' => 'Mot de passe d\'application',
        'password_keep' => '•••••••• (laisser inchangé)',
        'password_help' => 'Nextcloud : Paramètres → Sécurité → Mot de passe d\'application. Stocké chiffré.',
        'default_folder' => 'Dossier par défaut',
        'active' => 'Actif',
        'sources' => 'Contenu répliqué',
        'source_document' => 'Documents (GED)',
        'source_invoice_pdf' => 'Factures (PDF)',
        'source_protocol_pdf' => 'Comptes rendus (PDF)',
        'sources_help' => 'Quel contenu est répliqué vers ce stockage. Sans sélection : documents publiés uniquement.',
    ],

    'folder' => [
        'heading' => 'Type de document → dossier',
        'help' => 'Associe les types de document à un sous-dossier (relatif à l\'URL de la collection). Sans correspondance, le dossier par défaut s\'applique.',
        'type_placeholder' => '— type de document —',
        'path_placeholder' => 'Sous-dossier',
    ],

    'flash' => [
        'saved' => 'Stockage WebDAV enregistré.',
        'mirror_done' => 'Copie lancée.',
        'disconnected' => 'Stockage WebDAV déconnecté. Les fichiers déjà copiés sont conservés en externe.',
        'no_connection' => 'Aucun stockage WebDAV actif.',
        'invalid_url' => 'L\'URL de la collection doit commencer par http:// ou https://.',
        'password_required' => 'Un nouveau stockage nécessite un mot de passe d\'application.',
    ],
];
