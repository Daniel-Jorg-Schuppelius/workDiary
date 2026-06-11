<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : knowledge.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Base de connaissances',
        'links' => 'Historique des problèmes',
        'linked' => 'Articles liés',
        'suggestions' => 'Suggestions',
    ],

    'subtitle' => 'Problèmes connus, étapes de résolution et notes internes du quotidien.',

    'field' => [
        'title' => 'Titre',
        'category' => 'Catégorie',
        'tags' => 'Tags',
        'status' => 'Statut',
        'problem' => 'Description du problème',
        'solution' => 'Étapes de résolution',
        'helpful' => 'Évaluation',
        'creator' => 'Créé par',
        'published_at' => 'Publié le',
        'updated_at' => 'Dernière modification',
    ],

    'action' => [
        'create' => 'Créer un article',
        'create_from_subject' => 'Créer un article à partir de ceci',
        'edit' => 'Modifier',
        'save' => 'Enregistrer',
        'show' => 'Afficher',
        'publish' => 'Publier',
        'archive' => 'Archiver',
        'delete' => 'Supprimer',
        'link' => 'Lier',
        'unlink' => 'Supprimer le lien',
        'back' => 'Retour',
    ],

    'filter' => [
        'all' => 'Tous',
        'search' => 'Recherche',
        'search_placeholder' => 'Rechercher dans le titre, le problème ou la solution',
        'sort' => 'Tri',
        'sort_newest' => 'Les plus récents d’abord',
        'sort_helpful' => 'Les plus utiles d’abord',
    ],

    'feedback' => [
        'title' => 'Cet article vous a-t-il aidé ?',
        'helpful' => 'Cela a aidé',
        'not_helpful' => 'Cela n’a pas aidé',
        'already_voted' => 'Vous avez déjà voté — voter à nouveau modifie votre vote.',
    ],

    'link_kind' => [
        'diary' => 'Intervention',
        'asset' => 'Équipement',
        'customer' => 'Client',
        'protocol' => 'Protocole',
    ],

    'hint' => [
        'category' => 'p. ex. imprimante, réseau, chauffage …',
        'tags' => 'Séparés par des virgules, p. ex. firmware, modele-x',
        'problem' => 'Quel symptôme/problème survient-il ?',
        'solution' => 'Quelles étapes mènent à la solution ?',
    ],

    'flash' => [
        'created' => 'L’article a été créé.',
        'updated' => 'L’article a été mis à jour.',
        'published' => 'L’article a été publié.',
        'archived' => 'L’article a été archivé.',
        'deleted' => 'L’article a été supprimé.',
        'feedback_saved' => 'Merci pour votre évaluation.',
        'linked' => 'L’article a été lié.',
        'unlinked' => 'Le lien a été supprimé.',
    ],

    'empty' => 'Aucun article de connaissances pour le moment.',
    'empty_title' => 'Aucun article trouvé',
    'empty_filtered' => 'Aucun article ne correspond aux filtres actuels.',
    'empty_links' => 'Aucun lien pour le moment.',
    'empty_context' => 'Aucun article lié et aucune suggestion correspondante.',
    'confirm_archive' => 'Vraiment archiver cet article ? Il disparaîtra de la recherche et des suggestions.',
    'confirm_delete' => 'Vraiment supprimer cet article ?',
    'confirm_unlink' => 'Vraiment supprimer ce lien ?',
];
