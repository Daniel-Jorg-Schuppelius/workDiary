<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : search.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Recherche',
    'subtitle' => 'Trouver activités, clients et objets — qu’a-t-on fait, quand et pour quel client ?',
    'placeholder' => 'p. ex. smtp exchange, "smtp relay", -test',

    'group' => [
        'activities' => 'Activités',
    ],

    'source' => [
        'time_entry' => 'Saisie de temps',
        'diary_entry' => 'Mission',
        'timesheet' => 'Feuille de temps',
        'service_ticket' => 'Ticket',
        'protocol' => 'Procès-verbal',
        'open_issue' => 'Point ouvert',
        'communication_note' => 'Note de communication',
        'knowledge_article' => 'Article de connaissances',
        'remote_session' => 'Télémaintenance (non attribuée)',
        'learning_course' => 'Cours d’apprentissage',
    ],

    'field' => [
        'query' => 'Terme de recherche',
        'type' => 'Source',
        'all_types' => 'Toutes les sources',
        'person' => 'Personne',
        'all_persons' => 'Toutes les personnes',
        'customer' => 'Client',
        'all_customers' => 'Tous les clients',
        'foreign_customer' => 'Client final',
        'all_foreign_customers' => 'Tous les clients finaux',
        'sort' => 'Tri',
        'sort_relevance' => 'Meilleurs résultats d’abord',
        'sort_date' => 'Plus récents d’abord',
        'similar' => 'Orthographes similaires',
    ],

    'filter' => [
        'project' => 'Projet : :name',
        'remove' => 'Retirer le filtre',
    ],

    'notice' => [
        'corrections' => '« :word » n’apparaît pas — recherché également : :candidates.',
        'synonyms' => 'Également recherché : :list',
        'ignored' => 'Non pris en compte : :words',
    ],

    'aggregate' => [
        'title' => 'Clients & clients finaux',
        'without_customer' => 'sans client',
        'hits' => ':count résultat|:count résultats',
    ],

    'types' => [
        'title' => 'Sources',
    ],

    'hits' => [
        'title' => 'Activités',
        'open' => 'Ouvrir',
    ],

    'column' => [
        'date' => 'Date',
        'activity' => 'Activité',
        'customer' => 'Client › Client final / Projet',
        'person' => 'Personne',
        'duration' => 'Durée',
    ],

    'empty' => [
        'start' => 'Que cherchez-vous ?',
        'start_hint' => 'Des mots-clés suffisent, p. ex. « smtp exchange ». Tous les mots doivent apparaître — dans la saisie, le projet ou le client.',
        'none' => 'Aucun résultat.',
        'none_hint' => 'Essayez moins de mots ou activez « Orthographes similaires ».',
    ],

    'entities' => [
        'title' => 'Données de base & objets',
        'more' => 'Tous les résultats de ce groupe →',
        'back' => '← Retour à tous les résultats',
    ],

    'box' => [
        'title' => 'Rechercher dans les activités',
        'label' => 'Terme de recherche',
        'placeholder' => 'Mots-clés, p. ex. smtp exchange',
        'placeholder_customer' => 'Qu’a-t-on fait pour ce client ou ses clients finaux ?',
        'placeholder_foreign_customer' => 'Qu’a-t-on fait chez ce client final ?',
        'hint_customer' => 'Recherche dans les temps, missions, feuilles de temps, tickets, procès-verbaux et notes du client et de tous ses clients finaux. Sans terme de recherche, les activités les plus récentes s’affichent.',
        'hint_foreign_customer' => 'Recherche dans toutes les activités chez ce client final. Sans terme de recherche, les plus récentes s’affichent.',
        'submit' => 'Rechercher',
        'project_action' => 'Rechercher dans les activités',
    ],

    'open' => [
        'range_set' => 'Période réglée sur le :date pour que l’entrée apparaisse dans la liste.',
    ],

    'palette' => [
        'placeholder' => 'Rechercher activités, clients, projets, objets …',
    ],

    'ai' => [
        'action' => 'Réponse IA',
        'source_hint' => 'Recherche « :query » · :count résultats',
        'customer_alias' => 'Client :letter',
        'no_hits' => 'Aucun résultat à résumer.',
    ],

    'synonyms' => [
        'title' => 'Synonymes de recherche',
        'subtitle' => 'Termes de même sens : chercher l’un trouve aussi les autres.',
        'notice' => 'Exemple : si « smtp, mailrelay, sendeconnector » forment un groupe, une recherche sur « smtp » trouve aussi les saisies qui ne mentionnent que « Sendeconnector ». S’applique à toute l’organisation.',
        'legend' => 'Groupe de synonymes',
        'terms_help' => 'Un terme par ligne (ou séparés par des virgules), au moins deux, au plus 20. Les termes de plusieurs mots comme « send connector » sont autorisés.',
        'empty' => 'Aucun groupe de synonymes pour le moment.',
        'delete_confirm' => 'Supprimer ce groupe de synonymes ? La recherche ne trouvera plus les termes les uns par les autres.',
        'field' => [
            'terms' => 'Termes',
            'creator' => 'Créé par',
            'active' => 'Actif',
            'enabled_yes' => 'Oui',
            'enabled_no' => 'Non',
        ],
        'action' => [
            'new' => 'Créer un groupe',
            'edit' => 'Modifier le groupe',
            'submit' => 'Enregistrer',
            'activate' => 'Activer',
            'deactivate' => 'Désactiver',
            'delete' => 'Supprimer',
            'preset_it' => 'Reprendre le modèle IT',
        ],
        'flash' => [
            'saved' => 'Groupe de synonymes créé.',
            'updated' => 'Groupe de synonymes mis à jour.',
            'deleted' => 'Groupe de synonymes supprimé.',
            'activated' => 'Groupe de synonymes activé.',
            'deactivated' => 'Groupe de synonymes désactivé.',
            'preset_imported' => '{0} Tous les groupes du modèle existent déjà.|{1} :count groupe repris du modèle.|[2,*] :count groupes repris du modèle.',
        ],
        'validation' => [
            'min_terms' => 'Un groupe nécessite au moins deux termes différents.',
            'max_terms' => 'Au plus :max termes par groupe.',
            'term_length' => 'Un terme ne doit pas dépasser :max caractères.',
        ],
    ],
];
