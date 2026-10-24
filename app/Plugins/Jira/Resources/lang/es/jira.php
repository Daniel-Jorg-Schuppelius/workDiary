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
        'description' => 'Importa incidencias de proyectos de Jira Cloud seleccionados como tareas en proyectos de WorkDiary, solo lectura: título, descripción, estado mediante la categoría de estado y vencimiento. Los conflictos y casos por revisar van a la bandeja de entrada de integraciones.',
    ],
    'source' => 'Sitio',
    'task_link' => 'Abrir en Jira',
    'health' => [
        'not_configured' => 'Jira no está configurado para esta instalación (falta el registro de la aplicación del editor).',
        'not_connected' => 'No hay conexión con Jira con un sitio seleccionado.',
    ],
];
