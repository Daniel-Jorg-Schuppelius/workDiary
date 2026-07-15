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
        'subtitle' => 'Masquez les zones de menu dont vous n\'avez pas besoin. Le réglage ne vaut que pour vous, sur tous vos appareils.',
        'cosmetic_hint' => 'Masquer ne change aucun droit : la recherche, les favoris et les liens directs continuent de fonctionner. « Toutes les fonctions » permet de tout récupérer.',
        'sidebar_heading' => 'Navigation latérale',
        'hide_section' => 'masquer toute la section',
        'hide_group' => 'masquer le sous-groupe',
        'create_heading' => 'Création rapide (« Nouveau … »)',
        'create_hint' => 'Les groupes masqués n\'apparaissent plus dans le menu « Nouveau … » de la barre latérale.',
        'checkbox_hint' => 'Coché = masqué.',
        'saved' => 'Personnalisation du menu enregistrée.',
        'unhidden' => 'L\'entrée est de nouveau visible.',
    ],
    'functions' => [
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
