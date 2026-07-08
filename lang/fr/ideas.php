<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ideas.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Cartes d\'idées',
    ],
    'subtitle' => 'Cartes d\'idées privées et partagées — visibles uniquement par le propriétaire et les personnes explicitement autorisées.',
    'empty' => 'Aucune carte d\'idées.',
    'privacy_hint' => 'Les nouvelles cartes sont privées : visibles uniquement par vous jusqu\'à un partage explicite avec des personnes ou des équipes.',
    'confirm_delete' => 'Déplacer la carte vers la corbeille ?',

    'action' => [
        'create' => 'Créer une carte',
        'edit' => 'Modifier la carte',
        'archive' => 'Archiver',
        'unarchive' => 'Réactiver',
        'restore' => 'Restaurer',
    ],

    'col' => [
        'title' => 'Titre',
        'description' => 'Description',
        'owner' => 'Propriétaire',
        'visibility' => 'Visibilité',
        'nodes' => 'Nœuds',
        'updated' => 'Modifié',
        'actions' => 'Actions',
    ],

    'filter' => [
        'active' => 'Actives',
        'archived' => 'Archivées',
        'trashed' => 'Corbeille',
    ],

    'visibility' => [
        'private' => 'Privée',
        'shared' => 'Partagée',
    ],

    'share_role' => [
        'viewer' => 'Lecture',
        'editor' => 'Modification',
    ],

    'color' => [
        'default' => 'Neutre',
        'primary' => 'Bleu',
        'success' => 'Vert',
        'warning' => 'Jaune',
        'error' => 'Rouge',
        'info' => 'Turquoise',
    ],

    'node_status' => [
        'open' => 'Ouverte',
        'in_review' => 'En examen',
        'decided' => 'Décidée',
        'rejected' => 'Rejetée',
        'done' => 'Réalisée',
    ],

    'import' => [
        'action' => 'Importer',
        'title' => 'Importer une carte d\'idées',
        'submit' => 'Importer',
        'file' => 'Fichier',
        'hint' => 'FreeMind/Freeplane (.mm) ou OPML. Crée une nouvelle carte privée.',
        'done' => 'Carte importée.',
        'default_title' => 'Carte importée',
        'error' => [
            'invalid' => 'Le fichier n\'est pas un XML valide.',
            'unsupported' => 'Format non pris en charge (uniquement FreeMind .mm et OPML).',
            'empty' => 'Le fichier ne contient aucun nœud.',
            'too_deep' => 'La structure est trop profondément imbriquée.',
            'too_large' => 'La carte comporte trop de nœuds.',
        ],
    ],

    'legend' => [
        'context' => 'Contexte (facultatif)',
        'map' => 'Carte',
    ],

    'outline' => [
        'title' => 'Plan',
        'empty' => 'Cette carte n\'a pas encore de nœuds.',
    ],

    'flash' => [
        'created' => 'Carte créée.',
        'updated' => 'Carte enregistrée.',
        'archived' => 'Carte archivée.',
        'unarchived' => 'Carte réactivée.',
        'deleted' => 'Carte déplacée vers la corbeille.',
        'restored' => 'Carte restaurée.',
        'owner_invalid' => 'Nouveau propriétaire non valide.',
        'ownership_transferred' => 'Propriété transférée.',
        'share_granted' => 'Partage accordé.',
        'share_revoked' => 'Partage retiré.',
        'share_invalid' => 'Sélection de partage non valide (exactement une personne ou une équipe).',
    ],

    'share' => [
        'title' => 'Partages',
        'none' => 'Cette carte est privée — aucun partage.',
        'user' => 'Personne',
        'team' => 'Équipe',
        'role' => 'Rôle',
        'add' => 'Partager',
        'revoke' => 'Retirer le partage',
        'hint' => 'Exactement une personne OU une équipe par partage. L\'appartenance à l\'équipe est vérifiée à l\'accès.',
    ],

    'notification' => [
        'shared' => ':actor a partagé une carte d\'idées avec vous.',
    ],

    'export' => [
        'generated_at' => 'Créé le',
        'footer_note' => 'Export de la vue plan — les positions du canevas figurent dans l\'export JSON.',
    ],

    'context' => [
        'customer' => 'Client',
        'project' => 'Projet',
    ],

    'convert' => [
        'done' => 'Transféré :',
        'already' => 'Déjà transféré :',
        'error' => [
            'module_disabled' => 'Le module cible n\'est pas activé.',
            'target_not_allowed' => 'Cette cible n\'est pas autorisée.',
        ],
    ],

    'editor' => [
        'outline' => 'Plan',
        'canvas' => 'Carte',
        'saving' => 'Enregistrement …',
        'undo_delete' => 'Annuler la suppression',
        'keys_hint' => 'Entrée : nouveau nœud · Tab : indenter · Alt+↑/↓ : déplacer · F2 : renommer',
        'conflict_title' => 'Modification simultanée détectée — votre version était obsolète.',
        'conflict_take_server' => 'Prendre la version du serveur',
        'conflict_retry_mine' => 'Réappliquer ma modification',
        'new_node' => 'Nouvelle idée',
        'convert_task' => 'En tâche',
        'convert_project' => 'En projet',
        'convert_knowledge' => 'En article de connaissance',
        'confirm_delete_node' => 'Déplacer le nœud et ses sous-nœuds vers la corbeille ?',
        'add_child' => 'Ajouter un sous-nœud',
        'rename' => 'Renommer',
        'details' => 'Détails',
        'move_up' => 'Monter',
        'move_down' => 'Descendre',
        'indent' => 'Indenter',
        'outdent' => 'Désindenter',
        'delete' => 'Supprimer',
        'expand' => 'Déplier la branche',
        'collapse' => 'Replier la branche',
        'zoom_in' => 'Agrandir',
        'zoom_out' => 'Réduire',
        'zoom_reset' => 'Zoom à 100 %',
        'fit' => 'Ajuster la vue',
        'arrange' => 'Réorganiser',
        'arrange_hint' => 'Réorganiser automatiquement tous les nœuds en arbre',
        'canvas_large' => 'Grand espace de travail',
        'canvas_small' => 'Espace de travail compact',
        'canvas_keys_hint' => 'Tab : sous-nœud · Entrée : nœud frère · double-clic sur la zone : nouvelle idée · glisser sur un nœud : rattacher',
        'canvas_a11y_hint' => 'Édition accessible dans la vue plan.',
        'export_svg' => 'Exporter en image SVG',
        'export_png' => 'Exporter en image PNG',
        'history' => 'Historique',
        'history_empty' => 'Aucune modification pour le moment.',
        'presence_suffix' => 'modifie actuellement',
        'note' => 'Note',
        'color' => 'Couleur',
        'status' => 'Statut',
        'status_none' => '— aucun statut',
    ],

    'error' => [
        'conflict' => 'Le nœud a été modifié entre-temps — veuillez vérifier l\'état actuel.',
        'cycle' => 'Un nœud ne peut pas être déplacé sous l\'un de ses propres descendants.',
        'root_immovable' => 'Le nœud racine ne peut être ni déplacé ni supprimé.',
        'foreign_node' => 'Le nœud n\'appartient pas à cette carte.',
    ],
];
