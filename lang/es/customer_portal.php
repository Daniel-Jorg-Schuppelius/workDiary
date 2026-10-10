<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : customer_portal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Kundenportal: Passwort vergessen, Zugang zurücksetzen, Rechnungsdokument (MVP-1096/1097).
return [
    'password' => [
        'mail_subject' => 'Restablecer su contraseña del portal de clientes de :org',
        'mail_heading' => 'Restablecer contraseña',
        'mail_intro' => 'Se ha solicitado restablecer la contraseña de su acceso al portal de clientes de :org. Con el siguiente enlace puede establecer una nueva contraseña.',
        'mail_validity' => 'El enlace es de un solo uso y es válido durante :minutes minutos.',
        'mail_ignore' => 'Si usted no lo ha solicitado, ignore este correo — su contraseña actual sigue siendo válida.',
        'sessions_hint' => 'Con la nueva contraseña se cierran todas las sesiones abiertas de este acceso. Un segundo factor ya configurado se mantiene.',
    ],
    'reset' => [
        'action' => 'Restablecer acceso',
        'confirm_title' => 'Restablecer acceso al portal',
        'confirm_message' => 'La contraseña actual deja de ser válida de inmediato, se cierran todas las sesiones y :email recibe una nueva invitación. Los métodos de dos factores configurados se mantienen.',
        'confirm_label' => 'Restablecer',
        'flash' => 'Acceso restablecido — nueva invitación enviada a :email.',
        'mail_heading' => 'Su acceso al portal de clientes se ha restablecido',
        'mail_intro' => ':org ha restablecido su acceso al portal de clientes. Su contraseña anterior ya no es válida. Con el siguiente enlace puede establecer una nueva contraseña y después iniciar sesión.',
    ],
    'second_factor' => [
        'action' => 'Restablecer el segundo factor',
        'confirm_title' => 'Restablecer el segundo factor',
        'confirm_message' => 'Se eliminan todos los métodos de dos factores de :email (aplicación, llaves de acceso, códigos de recuperación) y se cierran todas las sesiones. Utilícelo solo si el cliente ya no dispone de ningún factor y usted ha verificado su identidad.',
        'confirm_label' => 'Restablecer',
        'flash' => 'Segundo factor de :email restablecido — se ha informado al cliente.',
        'mail_subject' => 'Acceso con dos factores al portal de clientes de :org restablecido',
        'mail_heading' => 'Su segundo factor se ha restablecido',
        'mail_intro' => ':org ha eliminado los métodos de dos factores de su acceso al portal de clientes. Inicie sesión con su contraseña; si :org exige el inicio de sesión con dos factores, lo configurará de nuevo en ese momento.',
        'mail_unexpected' => 'Si no lo ha solicitado usted, póngase en contacto de inmediato con su persona de contacto en :org.',
    ],
    'invoices' => [
        'pdf_label' => 'Descargar la factura :number en PDF',
    ],
];
