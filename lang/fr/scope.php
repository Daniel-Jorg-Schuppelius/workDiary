<?php
/*
 * Created on   : Wed Jul 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : scope.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Espaces de travail — vues à focus commutables (Feature 082).
    'focus' => [
        'admin' => [
            'title' => 'Espaces de travail',
            'subtitle' => 'Choisissez les espaces de travail proposés dans le sélecteur, renommez-les et définissez un espace par défaut.',
            'hint' => 'Une simple suggestion : l’espace par défaut n’est jamais imposé — chacun peut changer à tout moment. Masquer ne modifie aucune autorisation.',
            'list_heading' => 'Espaces proposés',
            'configured_at' => 'Dernière modification : :date',
            'mandatory' => 'toujours disponible',
            'is_default' => 'Par défaut',
            'rename' => 'Nom affiché',
            'offered' => 'proposé',
            'set_default' => 'Par défaut',
            'saved' => 'Espaces de travail enregistrés.',
        ],
        'switcher' => 'Changer d’espace de travail',
        'eyebrow' => 'Espace de travail',
        'all' => 'Tout afficher',
        'active' => 'Actif',
        'reveal' => 'Tout afficher',
        'reveal_off' => 'Afficher uniquement le focus',
        'dialog' => [
            'eyebrow' => 'Cibler l’affichage',
            'title' => 'Sur quoi travaillez-vous ?',
            'subtitle' => 'Choisissez un espace de travail — la navigation n’affiche alors que les domaines pertinents. Rien n’est supprimé ni verrouillé ; vous pouvez changer à tout moment.',
            'footnote' => 'Les domaines masqués restent accessibles via la recherche globale et « Tout afficher ».',
        ],
        'flash' => [
            'unknown' => 'Espace de travail inconnu.',
            'switched' => 'Espace de travail « :name » actif.',
        ],
        'personal' => [
            'title' => 'Espace de travail personnel',
            'description' => 'Votre propre sélection d’entrées de menu.',
            'heading' => 'Espaces de travail personnels',
            'manage' => 'Gérer les espaces de travail personnels',
        ],
    ],
    'workspace' => [
        'title' => 'Espaces de travail personnels',
        'subtitle' => 'Composez vos propres espaces de travail à partir des entrées de menu. Ils apparaissent dans le sélecteur à côté des vues prédéfinies et ne font que masquer — ils ne modifient jamais les droits.',
        'create' => 'Nouvel espace de travail',
        'edit' => 'Modifier l’espace de travail',
        'empty' => 'Aucun espace de travail personnel',
        'name' => 'Nom',
        'icon' => 'Icône',
        'sort' => 'Ordre',
        'items' => 'Entrées de menu',
        'available' => 'Disponibles',
        'selected' => 'Sélectionnées',
        'items_hint' => 'Seules les entrées que vous pouvez déjà voir sont proposées. Définissez l’ordre par glisser-déposer ou avec les boutons.',
        'add' => 'Ajouter',
        'remove' => 'Retirer',
        'move_up' => 'Monter',
        'move_down' => 'Descendre',
        'drag_hint' => 'Glissez pour trier — ou utilisez les boutons « Monter »/« Descendre ».',
        'count' => ':count entrées de menu',
        'active' => 'Actif',
        'delete_title' => 'Supprimer l’espace de travail',
        'delete_confirm' => 'L’espace de travail sera supprimé. Les entrées de menu et les droits restent inchangés.',
        'error' => [
            'no_items' => 'Sélectionnez au moins une entrée de menu.',
            'unknown_item' => 'Au moins une entrée de menu n’est pas disponible pour vous.',
        ],
        'flash' => [
            'created' => 'Espace de travail créé.',
            'updated' => 'Espace de travail enregistré.',
            'deleted' => 'Espace de travail supprimé.',
        ],
    ],
    'title' => [
        'index' => 'Périmètre fonctionnel',
    ],
    'nav' => [
        'customize' => 'Personnaliser le menu',
        'functions' => 'Toutes les fonctions',
    ],
    'page' => [
        'subtitle' => 'Définissez le périmètre fonctionnel visible de l\'organisation : des préréglages pour démarrer vite, ou module par module.',
        'no_data_loss' => 'La désactivation ne fait que masquer les modules et verrouiller leurs pages — aucune donnée n\'est supprimée. Tout revient à la réactivation.',
    ],
    'presets' => [
        'heading' => 'Préréglages',
        'hint' => 'Un préréglage est un raccourci : il bascule la liste de modules ci-dessous en une seule étape. Vous pouvez ensuite affiner individuellement.',
        'apply' => 'Appliquer le préréglage « :preset »',
        'all_modules' => 'Tous les modules sous licence',
        'module_count' => '{1} :count module supplémentaire|[2,*] :count modules supplémentaires',
    ],
    'recommendation' => [
        'heading' => 'Recommandation du profil métier',
        'hint' => 'Le profil métier installé « :profile » recommande les modules suivants.',
        'apply' => 'Appliquer la recommandation',
    ],
    'modules' => [
        'heading' => 'Définir les modules individuellement',
        'configured_at' => 'Dernière configuration : :date',
        'not_licensed_hint' => 'Non inclus dans le plan actuel — extensible via la gestion des licences.',
    ],
    'flash' => [
        'saved' => 'Périmètre fonctionnel enregistré (:disabled désactivés, :enabled activés). Aucune donnée supprimée.',
        'no_recommendation' => 'Aucune recommandation de profil métier pour cette organisation.',
    ],
    'customize' => [
        'subtitle' => 'Activez ce qui doit apparaître dans votre menu — désactivez ce dont vous n\'avez pas besoin. Ne vaut que pour vous, sur tous vos appareils.',
        'cosmetic_hint' => 'Masquer ne change aucun droit : la recherche, les favoris et les liens directs continuent de fonctionner. « Toutes les fonctions » permet de tout récupérer.',
        'sidebar_heading' => 'Navigation latérale',
        'hide_section' => 'masquer toute la section',
        'hide_group' => 'masquer le sous-groupe',
        'create_heading' => 'Création rapide (« Nouveau … »)',
        'create_hint' => 'Les groupes masqués n\'apparaissent plus dans le menu « Nouveau … » de la barre latérale.',
        'checkbox_hint' => 'Activé = visible dans le menu.',
        'saved' => 'Personnalisation du menu enregistrée.',
        'unhidden' => 'L\'entrée est de nouveau visible.',
    ],
    'functions' => [
        'focus_banner' => 'Espace de travail actif « :name ». Les domaines masqués sont indiqués ci-dessous — ils restent accessibles ici.',
        'in_focus_hidden' => 'Masqué par l’espace de travail',
        'show_all' => 'Tout afficher',
        'subtitle' => 'Vue d\'ensemble de toutes les zones et de leur état — y compris ce qui est masqué, désactivé ou non licencié.',
        'state' => [
            'hidden_section' => 'Section masquée',
            'org_disabled' => 'Désactivé par l\'organisation',
            'hidden_by_me' => 'Masqué par moi',
        ],
        'action' => [
            'unhide' => 'Afficher',
            'enable_module' => 'Ouvrir le périmètre fonctionnel',
        ],
        'upsell_hint' => 'Ce module n\'est pas inclus dans le plan actuel.',
    ],
];
