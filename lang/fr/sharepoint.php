<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sharepoint.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Stockage SharePoint',
    'intro' => 'Les documents validés sont reflétés par type de document dans une bibliothèque de documents SharePoint via Microsoft Graph — avec preuve de transfert (hachage, heure, cible). WorkDiary reste la référence ; les modifications externes des fichiers reflétés apparaissent comme des conflits, jamais reprises en silence.',
    'plugin_description' => 'Reflète les documents validés dans une bibliothèque de documents SharePoint via Microsoft Graph — avec preuve de transfert et affichage des conflits, sans canal retour.',
    'not_configured_hint' => 'SHAREPOINT_CLIENT_ID/SECRET (ou les valeurs de repli MSGRAPH_*) ne sont pas définis — la connexion ne peut être établie qu\'après l\'enregistrement de l\'application dans le tenant Microsoft.',

    'health' => [
        'badge_ok' => 'Connecté',
        'badge_failing' => 'Inaccessible',
        'badge_inactive' => 'Inactif',
        'not_configured' => 'SharePoint n\'est pas configuré (SHAREPOINT_/MSGRAPH_CLIENT_ID/SECRET manquants).',
        'no_org_context' => 'Configuré (aucune organisation dans le contexte).',
        'no_connection' => 'Aucune connexion SharePoint établie.',
        'inactive' => 'La connexion SharePoint est déconnectée, en pause ou sans bibliothèque cible.',
        'ok' => 'Connecté — bibliothèque cible accessible.',
        'failing' => 'Microsoft Graph inaccessible ou accès refusé.',
        'error' => 'Erreur Microsoft Graph (:class).',
    ],

    'action' => [
        'connect' => 'Connecter avec Microsoft 365',
        'mirror' => 'Refléter maintenant',
        'disconnect' => 'Déconnecter',
        'save' => 'Enregistrer',
    ],

    'target' => [
        'heading' => 'Cible : site + bibliothèque de documents',
        'help' => 'Recherchez d\'abord un site, puis choisissez la bibliothèque de documents. Les deux sont validés côté serveur via Microsoft Graph — avec Sites.Selected, seuls les sites autorisés apparaissent.',
        'current' => 'Cible actuelle',
        'search' => 'Rechercher un site',
        'search_placeholder' => 'Nom du site ou mot-clé',
        'search_action' => 'Rechercher',
        'no_sites' => 'Aucun site trouvé (vérifiez le terme de recherche ; avec Sites.Selected, l\'administrateur du tenant doit autoriser le site).',
        'selected' => 'Sélectionné',
        'drive' => 'Bibliothèque de documents',
        'no_drives' => 'Aucune bibliothèque de documents trouvée dans ce site.',
    ],

    'settings' => [
        'heading' => 'Règles de dossiers + sources',
    ],

    'field' => [
        'default_folder' => 'Dossier par défaut',
        'active' => 'Actif',
        'sources' => 'Contenus reflétés',
        'source_document' => 'Documents (GED)',
        'source_invoice_pdf' => 'Factures (PDF)',
        'source_protocol_pdf' => 'Protocoles (PDF)',
        'sources_help' => 'Quels contenus sont reflétés dans cette bibliothèque. Sans sélection, uniquement les documents validés.',
    ],

    'folder' => [
        'heading' => 'Type de document → dossier',
        'help' => 'Associe les types de documents à un sous-dossier (relatif à la bibliothèque). Sans correspondance, le dossier par défaut s\'applique.',
        'type_placeholder' => '— type de document —',
        'path_placeholder' => 'Sous-dossier',
    ],

    'conflict' => [
        'subtitle' => 'Modification externe détectée — réplication suspendue (pas d\'écrasement).',
        'action' => [
            'overwrite' => 'Écraser le distant',
            'import' => 'Importer comme nouvelle version',
            'detach' => 'Détacher la réplication',
        ],
        'confirm' => [
            'overwrite' => 'Écraser le fichier externe avec l\'état local ? La modification externe sera perdue.',
            'import' => 'Reprendre l\'état externe comme nouvelle version locale ?',
            'detach' => 'Détacher définitivement la réplication de ce document ? La connexion reste active.',
        ],
        'flash' => [
            'overwritten' => 'Fichier externe écrasé avec l\'état local.',
            'imported' => 'État externe importé comme nouvelle version locale.',
            'detached' => 'Réplication de ce document détachée.',
            'failed' => 'Échec de la résolution du conflit : :reason',
        ],
        'import_note' => 'Importé depuis SharePoint (résolution de conflit).',
    ],

    'flash' => [
        'not_configured' => 'SharePoint n\'est pas configuré (ID client/secret manquants).',
        'state_invalid' => 'Le flux OAuth a expiré ou est invalide — veuillez vous reconnecter.',
        'oauth_denied' => 'Microsoft n\'a pas renvoyé de code d\'autorisation (flux annulé ?).',
        'oauth_failed' => 'Échange de jeton échoué (:class).',
        'connected' => 'Connecté avec Microsoft 365. Choisissez maintenant le site + la bibliothèque.',
        'disconnected' => 'Connexion SharePoint déconnectée. Les fichiers déjà reflétés restent en externe.',
        'no_connection' => 'Aucune connexion SharePoint active disponible.',
        'site_invalid' => 'Le site choisi est inaccessible ou non autorisé.',
        'drive_invalid' => 'La bibliothèque de documents choisie n\'appartient pas au site choisi.',
        'target_saved' => 'Bibliothèque cible enregistrée.',
        'saved' => 'Paramètres SharePoint enregistrés.',
        'mirror_done' => 'Réplication lancée.',
    ],
];
