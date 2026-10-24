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
        'description' => 'Importa tareas de proyectos de Asana seleccionados en proyectos de WorkDiary, solo lectura: título, notas, finalización y vencimiento. Los conflictos y casos por revisar van a la bandeja de entrada de integraciones.',
    ],
    'source' => 'Espacio de trabajo',
    'task_link' => 'Abrir en Asana',
    'health' => [
        'not_configured' => 'Asana no está configurado para esta instalación (falta el registro de la aplicación del editor).',
        'not_connected' => 'No hay conexión con Asana con un espacio de trabajo seleccionado.',
    ],
];
