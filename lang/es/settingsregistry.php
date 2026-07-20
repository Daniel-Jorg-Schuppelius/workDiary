<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : settingsregistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Configuración (registro)',
        'subtitle' => 'Configuraciones de sistema y organización registradas — con valor efectivo, origen y restablecimiento.',
        'help_text' => 'Solo las claves declaradas en el registro pueden modificarse aquí; la validación, la sensibilidad y la auditoría se definen por clave. Los valores de infraestructura (APP_KEY, base de datos, transporte de correo) no aparecen aquí deliberadamente.',
    ],
    'scopes' => [
        'system' => 'Sistema (operador)',
        'organization' => 'Organización',
        'user' => 'Usuario',
    ],
    'sources' => [
        'organization' => 'Anulación de org.',
        'system' => 'Anulación del sistema',
        'config' => 'Archivo de configuración',
        'default' => 'Valor predeterminado',
    ],
    'field' => [
        'search' => 'Buscar claves…',
        'sensitive' => 'Sensible',
        'sensitive_placeholder' => 'Introducir nuevo valor (valor actual oculto)',
        'affects' => 'Afecta a',
    ],
    'action' => [
        'save' => 'Guardar',
        'reset' => 'Restablecer predeterminado',
        'history' => 'Historial',
        'export' => 'Exportar (JSON)',
    ],
    'empty' => [
        'title' => 'No se encontraron configuraciones',
        'message' => 'No existen claves de registro para este ámbito o término de búsqueda.',
        'history' => 'Aún no se han registrado cambios.',
    ],
    'flash' => [
        'saved' => 'Configuración :key guardada.',
        'reset' => 'Configuración :key restablecida al valor predeterminado.',
    ],
];
