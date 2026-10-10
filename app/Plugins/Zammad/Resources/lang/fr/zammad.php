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
    'intro' => 'Les tickets ouverts visibles par le jeton API arrivent comme tâches dans WorkDiary — pour le suivi du temps, les justificatifs et la facturation. Avec « Groupes associés uniquement », seuls les groupes associés à un projet. Le système de tickets reste la référence ; une réimportation ne crée jamais de doublons.',

    'health' => [
        'ok' => 'Connecté',
        'failing' => 'Injoignable',
        'inactive' => 'Inactif',
    ],

    'action' => [
        'sync' => 'Importer maintenant',
        'disconnect' => 'Déconnecter',
        'save' => 'Enregistrer',
        'switch_target' => 'Changer de cible',
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
        'webhook_url' => 'Adresse du webhook (à saisir dans Zammad sous Webhook comme point de terminaison, avec le secret comme jeton de signature HMAC SHA1)',
        'default_project' => 'Projet par défaut',
        'no_project' => '— sans projet (global) —',
        'active' => 'Actif',
        'resolved_state' => 'Retour de statut (état cible)',
        'resolved_state_help' => 'Optionnel : état cible du ticket lorsque la tâche est terminée (p. ex. « closed »). Vide = désactivé.',
        'time_unit' => 'Imputation du temps dans le ticket',
        'time_unit_off' => 'Désactivé',
        'time_unit_minute' => 'Minutes',
        'time_unit_hour' => 'Heures',
        'time_unit_help' => 'Optionnel : impute dans le ticket les temps saisis sur les tâches liées, comme suivi du temps. L\'unité doit correspondre à l\'unité de suivi du temps de Zammad. Désactivé = aucune imputation.',
        'allow_private_network' => 'Autoriser les adresses privées/internes',
        'allow_private_network_help' => 'À activer uniquement si Zammad se trouve sur votre propre réseau (p. ex. 192.168.x.x). Cette action est auditée et ne prend effet que si l\'exploitant l\'autorise.',
    ],

    'queue' => [
        'heading' => 'File → projet',
        'help' => 'Associe les groupes Zammad (ID de groupe) à un projet WorkDiary. Sans correspondance, le projet par défaut s\'applique, sinon la tâche est créée globalement.',
        'group_id' => 'ID de groupe',
        'limited' => 'Groupes associés uniquement',
        'limited_help' => 'Activé : seuls les tickets des groupes associés ici à un projet arrivent comme tâches. Désactivé : tous les tickets visibles par le jeton API.',
    ],

    'flash' => [
        'saved' => 'Connexion Zammad enregistrée.',
        'sync_done' => 'Importation des tickets lancée.',
        'disconnected' => 'Connexion Zammad déconnectée. Les tâches et les liens sont conservés.',
        'no_connection' => 'Aucune connexion Zammad active.',
        'invalid_url' => 'L\'URL de l\'instance doit commencer par http:// ou https://.',
        'token_required' => 'Une nouvelle connexion nécessite un jeton API.',
        'private_url_blocked' => 'L\'URL de l\'instance pointe vers une adresse privée/interne. Pour un Zammad sur votre propre réseau, activez l\'autorisation des adresses privées.',
        'helpdesk_required' => 'Les tickets de service nécessitent le module Helpdesk.',
        'queue_required' => 'Veuillez choisir une file d\'attente.',
        'target_switched' => 'Cible des tickets modifiée.',
    ],

    'target' => [
        'heading' => 'Cible des tickets',
        'help' => 'Détermine si les nouveaux tickets arrivent comme tâches ou comme tickets de service d\'une file. Les tickets déjà importés restent où ils sont. Des conflits d\'affectation ouverts dans la boîte de rapprochement empêchent le changement.',
        'current' => 'Actuellement',
        'task' => 'Tâches',
        'service_ticket' => 'Tickets de service',
        'service_ticket_in' => 'Tickets de service dans « :queue »',
        'field' => 'Nouveaux tickets comme',
        'queue' => 'File d\'attente',
        'queue_help' => 'Uniquement pour les tickets de service. Zammad gère ensuite les tickets de cette file.',
        'no_queue' => '— choisir une file d\'attente —',
        'helpdesk_hint' => 'Les tickets de service nécessitent le module Helpdesk.',
        'no_queues_hint' => 'Il n\'existe encore aucune file. Créez-la sous Service desk → Files d\'attente.',
        'confirm_title' => 'Changer la cible des tickets',
        'confirm' => 'Les nouveaux tickets arriveront ensuite par la voie choisie ; les tickets déjà importés restent où ils sont. Le changement est journalisé.',
    ],

    'guard' => [
        'subject' => 'L\'URL de l\'instance',
        'private_hint' => 'Pour un Zammad sur votre propre réseau, l\'autorisation des adresses privées doit être activée sur la connexion.',
    ],

    'resolution' => [
        'note' => 'Résolu dans WorkDiary.',
    ],
];
