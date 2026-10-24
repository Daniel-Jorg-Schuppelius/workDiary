<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : akaunting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Refleja en modo de solo lectura facturas y facturas de proveedores de Akaunting en el flujo de documentos, con enlace al original. Los proveedores se vinculan mediante asignaciones existentes o su nombre.',
    ],
    'settings' => [
        'base_url' => 'Dirección',
        'base_url_help' => 'Dirección de la instalación de Akaunting, p. ej. https://contabilidad.example.com (sin /api).',
        'email' => 'E-mail',
        'email_help' => 'Inicio de sesión de un usuario de Akaunting con el permiso «leer API» y permiso de lectura sobre facturas y facturas de proveedor.',
        'password' => 'Contraseña',
        'company_id' => 'ID de la empresa',
        'company_id_help' => 'Número de la empresa en Akaunting; aparece en la dirección de la interfaz (p. ej. /1/…).',
        'pull_vouchers' => 'Obtener documentos',
        'pull_vouchers_help' => 'Solo con este ajuste la consulta horaria lee documentos. No escribe nada en Akaunting.',
        'allow_private_network' => 'Permitir direcciones privadas',
        'allow_private_network_help' => 'Solo para una instalación en su propia red: permite una dirección privada o interna. Se registra y solo surte efecto si el operador lo permite.',
    ],
    'health' => [
        'not_configured' => 'Akaunting no está configurado (falta la dirección o el acceso).',
    ],
];
