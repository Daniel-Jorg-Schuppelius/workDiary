<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : js.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'dialog' => [
        'check_input' => 'Veuillez vérifier votre saisie.',
        'save_failed' => 'Le dialogue n\'a pas pu être enregistré.',
        'load_failed' => 'Le dialogue n\'a pas pu être chargé.',
        'loading' => 'Chargement…',
        'open_in_new_tab' => 'Ouvrir la page dans un nouvel onglet',
        'switch_to_new' => 'Passer au nouveau mode',
        'switch_to_legacy' => 'Passer au mode hérité',
    ],
    'schedule' => [
        'move_failed' => 'Le déplacement a échoué.',
        'suggest_failed' => 'Impossible de charger les suggestions.',
    ],
    // Dienstplan-Oberflaeche (MVP-797): war zuvor fest verdrahtetes Deutsch.
    'schedule_ui' => [
        'shift_edit' => 'Modifier le poste',
        'shift_create' => 'Créer un poste',
        'shift_delete_confirm' => 'Supprimer vraiment ce poste ?',
        'shift_type_edit' => 'Modifier le type de poste',
        'shift_type_create' => 'Créer un type de poste',
        'shift_type_delete_confirm' => 'Supprimer vraiment ce type de poste ?',
        'save' => 'Enregistrer',
        'delete' => 'Supprimer',
        'close' => 'Fermer',
        'save_failed' => 'Échec de l\'enregistrement.',
        'delete_failed' => 'Échec de la suppression.',
        'publish_failed' => 'Échec de la publication.',
        'confirm_failed' => 'Échec de la confirmation.',
        'suggestions_title' => 'Suggestions d\'affectation',
        'col_employee' => 'Collaborateur',
        'col_score' => 'Score',
        'col_reason' => 'Motif',
    ],
    'bulk' => [
        'select_one' => 'Sélectionne d\'abord au moins une entrée.',
    ],
    'design' => [
        'inheritance' => '« :base » · :inherited/:total hérités, :own remplacés',
    ],
    // Chat-Oberflaeche (MVP-798): angepinnte Nachrichten werden per JSON geladen.
    'chat' => [
        'pinned_failed' => 'Impossible de charger les messages épinglés.',
        'pinned_empty' => 'Aucun message épinglé.',
    ],
    'kanban' => [
        'invalid_move' => 'Ce changement de statut n\'est pas prévu dans le flux de travail de la commande.',
        'not_allowed' => 'Vous n\'êtes pas autorisé à effectuer cette action sur la commande.',
        'handover_via_order' => 'La réception nécessite un protocole signé et s\'effectue directement dans la commande.',
        'no_targets' => 'Aucun déplacement autorisé n\'est actuellement possible pour cette carte.',
    ],
    'entry_bar' => [
        'options_failed' => 'Les tâches/commandes n\'ont pas pu être chargées.',
    ],
    'http' => [
        'session_expired' => 'Votre session a expiré — la page va être rechargée.',
    ],
    // KI-Tagvorschläge im Tag-Picker (Feature 143, MVP-711)
    'ai' => [
        'tags_no_text' => 'Saisissez d’abord un contenu — l’IA propose des tags à partir du texte.',
        'tags_none' => 'Aucun tag existant ne correspond au texte.',
        'tags_failed' => 'Proposition de tags IA impossible : :message',
        'tags_loading' => 'L’IA cherche des tags correspondants …',
    ],
    // Tastenkürzel-Übersicht (Feature 037, MVP-721): Labels der Registry resources/js/shortcuts.js
    'shortcuts' => [
        'help' => 'Ouvrir l\'aide contextuelle de la page actuelle',
        'title' => 'Raccourcis clavier',
        'scope' => [
            'global' => 'Global',
            'navigation' => 'Navigation',
            'search' => 'Recherche',
        ],
        'search' => 'Ouvrir la recherche globale',
        'shortcuts' => 'Afficher cet aperçu',
        'escape' => 'Fermer le dialogue ou la recherche',
        'search_move' => 'Se déplacer dans les résultats',
        'search_open' => 'Ouvrir le résultat',
        'go_diary' => 'Aller au journal',
        'go_customers' => 'Aller aux clients',
        'go_projects' => 'Aller aux projets',
        'new_entry' => 'Nouvelle entrée',
        'then' => 'puis',
    ],
    'quiz' => [
        'progress' => ':answered sur :total répondues',
    ],
];
