<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : zammad.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Zammad',
    'intro' => 'Les tickets d\'un groupe Zammad associé arrivent comme tâches dans WorkDiary — pour le suivi du temps, les justificatifs et la facturation. Le système de tickets reste la référence ; une réimportation ne crée jamais de doublons.',

    'health' => [
        'ok' => 'Connecté',
        'failing' => 'Injoignable',
        'inactive' => 'Inactif',
    ],

    'action' => [
        'sync' => 'Importer maintenant',
        'disconnect' => 'Déconnecter',
        'save' => 'Enregistrer',
    ],

    'connection' => [
        'heading' => 'Connexion',
    ],

    'field' => [
        'name' => 'Libellé',
        'base_url' => 'URL de l\'instance',
        'api_token' => 'Jeton API',
        'token_keep' => '•••••••• (laisser inchangé)',
        'token_help' => 'Zammad : Profil → Accès par jeton. Stocké chiffré.',
        'webhook_secret' => 'Secret du webhook (facultatif)',
        'webhook_help' => 'Secret partagé pour la signature du webhook (X-Hub-Signature). Vide = webhook désactivé, interrogation seule.',
        'default_project' => 'Projet par défaut',
        'no_project' => '— sans projet (global) —',
        'active' => 'Actif',
        'resolved_state' => 'Retour de statut (état cible)',
        'resolved_state_help' => 'Optionnel : état cible du ticket lorsque la tâche est terminée (p. ex. « closed »). Vide = désactivé.',
    ],

    'queue' => [
        'heading' => 'File → projet',
        'help' => 'Associe les groupes Zammad (ID de groupe) à un projet WorkDiary. Sans correspondance, le projet par défaut s\'applique, sinon la tâche est créée globalement.',
        'group_id' => 'ID de groupe',
    ],

    'flash' => [
        'saved' => 'Connexion Zammad enregistrée.',
        'sync_done' => 'Importation des tickets lancée.',
        'disconnected' => 'Connexion Zammad déconnectée. Les tâches et les liens sont conservés.',
        'no_connection' => 'Aucune connexion Zammad active.',
        'invalid_url' => 'L\'URL de l\'instance doit commencer par http:// ou https://.',
        'token_required' => 'Une nouvelle connexion nécessite un jeton API.',
    ],
    'resolution' => [
        'note' => 'Résolu dans WorkDiary.',
    ],
];
