<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : jira.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Importe les tickets de projets Jira Cloud sélectionnés comme tâches dans des projets WorkDiary, en lecture seule : titre, description, statut via la catégorie de statut et échéance. Les conflits et cas à vérifier vont dans la boîte de réception d’intégration.',
    ],
    'source' => 'Site',
    'task_link' => 'Ouvrir dans Jira',
    'health' => [
        'not_configured' => 'Jira n’est pas configuré pour cette installation (l’enregistrement de l’application de l’éditeur manque).',
        'not_connected' => 'Aucune connexion Jira avec un site sélectionné.',
    ],
];
