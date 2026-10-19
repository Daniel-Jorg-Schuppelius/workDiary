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
        'document_unavailable' => 'El documento aún no está disponible en el programa contable. Vuelva a intentarlo más tarde.',
    ],

    // Vista previa como cliente (fase 153, MVP-1229–1231).
    'preview' => [
        'action' => 'Ver portal',
        'action_as' => 'Ver como esta persona',
        'no_account' => 'Vista previa disponible tras crear un acceso',
        'running' => 'Vista previa activa como :name hasta las :until.',
        'stop' => 'Finalizar vista previa',
        'banner' => 'Vista previa: está viendo el portal como :name (:customer). Las acciones están bloqueadas.',
        'banner_until' => 'Termina a las :time.',
        'title_prefix' => 'Vista previa',
        'blocked' => 'Durante la vista previa las acciones están bloqueadas.',
        'blocked_page' => 'Esta página no está disponible durante la vista previa.',
        'nested_support' => 'Durante una sesión de soporte no es posible una vista previa.',
        'deactivated' => 'No hay vista previa para un acceso desactivado.',
        'ended_manual' => 'Vista previa finalizada.',
        'ended_replaced' => 'La vista previa anterior ha finalizado.',
        'ended_expired' => 'La vista previa ha caducado tras :minutes minutos.',
        'ended_actor_changed' => 'La vista previa ha finalizado porque la sesión iniciada ha cambiado.',
        'ended_permission_lost' => 'La vista previa ha finalizado porque falta el permiso.',
        'ended_organization_changed' => 'La vista previa ha finalizado porque la organización ha cambiado.',
        'ended_account_changed' => 'La vista previa ha finalizado porque la cuenta del portal ha cambiado.',
        'ended_account_deactivated' => 'La vista previa ha finalizado porque la cuenta del portal ya no está disponible.',
    ],
    // Acceso con contraseña inicial (MVP-1232).
    'direct' => [
        'action' => 'Crear acceso',
        'title' => 'Crear acceso al portal con contraseña inicial',
        'intro' => 'Recibirá una contraseña inicial aleatoria que se muestra una sola vez. Entréguela en persona o por teléfono, nunca por correo electrónico. En el primer inicio de sesión la persona establece su propia contraseña.',
        'submit' => 'Crear acceso',
        'flash' => 'Acceso para :email creado.',
        'password_heading' => 'Contraseña inicial para :email',
        'password_hint' => 'Se muestra una sola vez. Entréguela en persona o por teléfono, nunca por correo electrónico. En el primer inicio de sesión debe establecerse una contraseña propia.',
        'copy' => 'Copiar',
        'copied' => 'Copiado',
        'state_provisional' => 'Contraseña inicial pendiente',
        'reissue_action' => 'Generar contraseña inicial',
        'reissue_confirm_title' => 'Generar nueva contraseña inicial',
        'reissue_confirm_message' => 'La contraseña actual de :email deja de ser válida de inmediato, una invitación abierta caduca y todas las sesiones finalizan. La nueva contraseña inicial se muestra una sola vez; en el siguiente inicio de sesión debe establecerse una contraseña propia. Los métodos de dos factores se mantienen.',
        'reissue_confirm_label' => 'Generar',
        'reissue_flash' => 'Nueva contraseña inicial para :email generada.',
        'reauth_hint' => 'Crear un acceso con contraseña inicial requiere un inicio de sesión reciente. Confirme primero su contraseña y vuelva a abrir el diálogo después.',
        'reauth_action' => 'Confirmar contraseña',
    ],
    // Cambiar contraseña en el portal (MVP-1233).
    'password_change' => [
        'title' => 'Cambiar contraseña',
        'forced_hint' => 'Ha recibido una contraseña inicial. Establezca ahora su propia contraseña antes de usar el portal.',
        'intro' => 'Introduzca su contraseña actual y una nueva contraseña. Las sesiones de este acceso en otros dispositivos finalizarán.',
        'current' => 'Contraseña actual',
        'new' => 'Nueva contraseña',
        'confirm' => 'Repetir la nueva contraseña',
        'submit' => 'Guardar contraseña',
        'flash' => 'Su contraseña ha sido cambiada.',
        'profile_heading' => 'Contraseña',
        'profile_intro' => 'Cambie su contraseña si sospecha que alguien la conoce o si ha recibido una contraseña inicial.',
        'profile_link' => 'Cambiar contraseña',
    ],
];
