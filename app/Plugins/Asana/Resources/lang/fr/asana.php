<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : asana.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Importe les tâches de projets Asana sélectionnés dans des projets WorkDiary, en lecture seule : titre, notes, achèvement et échéance. Les conflits et cas à vérifier vont dans la boîte de réception d’intégration.',
    ],
    'source' => 'Espace de travail',
    'task_link' => 'Ouvrir dans Asana',
    'health' => [
        'not_configured' => 'Asana n’est pas configuré pour cette installation (l’enregistrement de l’application de l’éditeur manque).',
        'not_connected' => 'Aucune connexion Asana avec un espace de travail sélectionné.',
    ],
];
