<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : collections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Sammlungen (MVP-809, Feature 155).
return [
    'title' => [
        'index' => 'Collections',
        'tree' => 'Arborescence',
    ],
    'subtitle' => 'Organisez ensemble notes, cartes d’idées, articles, documents et contenus de formation — un contenu peut figurer dans plusieurs collections.',
    'action' => [
        'show_archived' => 'Afficher les archivées',
        'hide_archived' => 'Masquer les archivées',
        'create' => 'Créer une collection',
        'create_child' => 'Créer une sous-collection',
        'edit' => 'Modifier la collection',
        'save' => 'Enregistrer',
        'archive' => 'Archiver',
        'restore' => 'Restaurer',
        'remove_item' => 'Retirer de la collection',
        'add_to_collection' => 'Ajouter à une collection',
        'add' => 'Ajouter',
    ],
    'empty' => [
        'tree' => 'Aucune collection pour l’instant.',
        'selection' => 'Aucune collection sélectionnée.',
        'items' => 'Cette collection est vide — ou ne contient que des contenus que vous n’êtes pas autorisé à voir.',
    ],
    'help' => [
        'intro' => 'Une collection organise des contenus sans donner d’accès : chacun n’y voit que ce qu’il peut voir par ailleurs.',
        'add_from_detail' => 'Ajoutez des contenus via « Ajouter à une collection » sur leur page de détail.',
        'parent' => ':max niveaux au maximum.',
        'visibility' => 'Seule la personne qui l’a créée voit une collection privée.',
        'create_first' => 'Créez d’abord une collection sous « Collections ».',
        'multiple_membership' => 'Un contenu peut figurer dans plusieurs collections, sans copie.',
    ],
    'visibility' => [
        'organization' => 'Organisation',
        'private' => 'Privée',
    ],
    'badge' => [
        'archived' => 'Archivée',
        'already_in' => 'déjà présent',
    ],
    'field' => [
        'type' => 'Type',
        'title' => 'Titre',
        'added_by' => 'Ajouté',
        'actions' => 'Actions',
        'description' => 'Description',
        'parent' => 'Collection parente',
        'no_parent' => '— niveau supérieur —',
        'visibility' => 'Visibilité',
        'collection' => 'Collection',
    ],
    'flash' => [
        'created' => 'Collection créée.',
        'updated' => 'Collection enregistrée.',
        'archived' => 'Collection archivée.',
        'restored' => 'Collection restaurée.',
        'item_added' => 'Ajouté à « :collection ».',
        'item_removed' => 'Retiré de la collection.',
        'items_added' => '{0} Aucun contenu ajouté.|{1} Un contenu ajouté à « :collection ».|[2,*] :count contenus ajoutés à « :collection ».',
    ],
    'errors' => [
        'too_deep' => 'Les collections s’imbriquent sur :max niveaux au maximum.',
        'cycle' => 'Une collection ne peut pas se trouver sous elle-même ou sous l’une de ses sous-collections.',
        'type_not_allowed' => 'Ce type de contenu ne peut pas être ajouté à une collection.',
        'item_not_found' => 'Le contenu n’existe pas ou ne vous est pas visible.',
        'parent_invalid' => 'La collection parente choisie n’existe pas (plus).',
    ],
    'type' => [
        'note' => 'Note',
        'idea_map' => 'Carte d’idées',
        'knowledge_article' => 'Article de la base',
        'document' => 'Document',
        'learning_course' => 'Cours',
        'learning_path' => 'Parcours',
    ],
    'references' => [
        'title' => 'Références',
        'outgoing' => 'Renvoie à',
        'backlinks' => 'Mentionné dans',
        'action' => [
            'create' => 'Ajouter une référence',
            'remove' => 'Retirer la référence',
        ],
        'field' => [
            'target' => 'Cible',
            'search' => 'Rechercher un contenu …',
        ],
        'kind' => [
            'linked' => 'lié',
            'converted' => 'converti',
            'mentioned' => 'mentionné',
        ],
        'empty' => 'Aucune référence pour l’instant – ni depuis ici, ni vers ici.',
        'empty_picker' => 'Aucun autre contenu vers lequel vous pouvez renvoyer.',
        'help' => 'Une référence relie deux contenus sans donner accès : seules les personnes autorisées à ouvrir l’autre côté le voient.',
        'confirm_remove' => 'Retirer cette référence ? Les deux contenus sont conservés.',
        'flash' => [
            'added' => 'Référence vers « :title » ajoutée.',
            'removed' => 'Référence retirée.',
        ],
        'errors' => [
            'self' => 'Un contenu ne peut pas renvoyer à lui-même.',
            'not_removable' => 'Cette référence est gérée par son module, pas par cette liste.',
        ],
    ],
    'hub' => [
        'title' => 'Connaissances',
        'subtitle' => 'Notes, cartes d’idées, articles de connaissances, documents et contenus de formation au même endroit — classés par collections et mots-clés.',
        'search' => 'Rechercher dans les titres …',
        'all_types' => 'Tous les types',
        'all_contents' => 'Tous les contenus',
        'view_list' => 'Liste',
        'view_tiles' => 'Vignettes',
        'manage' => 'Gérer les collections',
        'tag_filter' => 'Filtre par mot-clé',
        'selected' => ':n contenus sélectionnés',
        'select_all' => 'Tout sélectionner',
        'select_item' => 'Sélectionner « :title »',
        'updated' => 'Modifié',
        'empty' => 'Aucun contenu trouvé.',
        'empty_hint' => 'Assouplissez les filtres ou choisissez une autre collection.',
        'add_hits' => 'Ajouter à une collection',
    ],
    'import' => [
        'untitled' => 'Sans titre',
        'source' => 'Source',
        'target' => 'Importer comme',
        'root_title' => 'Nom de la collection',
        'root_title_hint' => 'Vide : nom du dossier ou du bloc-notes. Une collection du même nom est réutilisée.',
        'rules' => 'En lecture seule et uniquement à la demande : les contenus déjà importés restent inchangés, rien n’est réécrit. Au plus :max nouveaux contenus par exécution — une exécution suivante importe le reste.',
        'origin' => 'Importé depuis :source le :date',
        'action' => [
            'start' => 'Lancer l’import',
        ],
        'obsidian' => [
            'title' => 'Importer depuis Obsidian',
            'action' => 'Importer Obsidian',
            'intro' => 'Lit un dossier Obsidian via une connexion de dossier existante de l’entrée de documents (Nextcloud, OneDrive, Dropbox, Google Drive). Les sous-dossiers deviennent des collections, les mots-clés YAML et les #mots-clés sont repris, les [[wikiliens]] deviennent des références.',
            'none' => 'Aucune connexion de dossier active. Configurez d’abord sous Administration › Entrée de documents cloud une connexion qui atteint le dossier du coffre Obsidian.',
            'connection' => 'Connexion de dossier',
            'vault_path' => 'Chemin du coffre',
            'vault_path_hint' => 'Relatif au dossier racine de la connexion ; vide = tout le dossier racine. .obsidian/ et .trash/ sont exclus.',
        ],
        'onenote' => [
            'title' => 'Importer depuis OneNote',
            'action' => 'Importer OneNote',
            'intro' => 'Importe un bloc-notes via la connexion OneNote en lecture seule : groupes de sections et sections deviennent des collections, chaque page une note ou un article. Le contenu de la page est importé sous forme de texte.',
            'none' => 'Aucun bloc-notes trouvé.',
            'error' => 'OneNote n’est pas joignable pour le moment — veuillez vérifier la connexion dans le panneau Microsoft 365.',
            'notebook' => 'Bloc-notes',
        ],
        'flash' => [
            'done' => 'Importé : :created nouveaux, :skipped déjà présents, :collections collections créées, :links références ajoutées.',
            'limited' => 'Limite de :max nouveaux contenus atteinte — une exécution suivante importe le reste.',
            'failed' => 'L’import a échoué. Les contenus déjà importés sont conservés ; une exécution suivante reprend.',
            'source_unavailable' => 'La source n’est pas (ou plus) disponible.',
            'notebook_invalid' => 'Le bloc-notes choisi n’est pas (ou plus) disponible.',
        ],
    ],
];
