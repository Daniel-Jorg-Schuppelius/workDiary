<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : intake_upload.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Upload-Kanal der Kundeneingänge (MVP-1078): Einstellungen und Health.
 */

return [
    'settings' => [
        'server_url' => 'Canal de subida: URL del servidor',
        'server_url_help' => 'Dirección de Nextcloud (https) para los enlaces de subida de las entradas de clientes, separada de la entrada de documentos y de la copia de seguridad.',
        'username' => 'Canal de subida: usuario',
        'app_password' => 'Canal de subida: contraseña de aplicación',
        'app_password_help' => 'Contraseña de aplicación revocable de Nextcloud (Configuración → Seguridad), nunca la contraseña de la cuenta.',
        'base_folder' => 'Canal de subida: carpeta base',
        'link_days' => 'Canal de subida: validez de los enlaces (días)',
        'link_days_help' => 'De 1 a 90 días; después, el enlace se revoca tras la última incorporación.',
    ],
    'health' => [
        'attention' => 'No se ha podido recoger al menos un enlace de subida de las entradas de clientes.',
    ],
];
