<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : dsar.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'portal' => [
        'title' => 'Solicitud de protección de datos',
        'subtitle' => 'Sus derechos como interesado',
        'footer' => 'Esta página sirve exclusivamente para ejercer sus derechos como interesado. No envíe aquí datos de pago ni credenciales de acceso.',
    ],

    'landing' => [
        'title' => 'Presentar una solicitud de protección de datos',
        'intro' => 'Mediante este procedimiento las personas interesadas pueden ejercer sus derechos conforme al Reglamento General de Protección de Datos.',
        'no_link' => 'Para presentar una solicitud necesita el enlace del responsable del tratamiento. Diríjase a la organización cuyos datos le conciernen.',
        'rights' => 'Tipos de solicitud disponibles',
    ],

    'legal_note' => 'Esta información es orientativa y no constituye asesoramiento jurídico. Prevalece el texto legal.',
    'privacy_notice' => 'Sus datos se utilizan únicamente para tramitar esta solicitud, se almacenan cifrados y se eliminan al vencer el plazo de conservación. La base jurídica es el art. 6.1.c) del RGPD en relación con los arts. 15 a 21 del RGPD.',
    'identity_hint' => 'Antes de facilitar información, el responsable verifica su identidad (art. 12.6 del RGPD) y puede ponerse en contacto con usted por separado a tal fin.',

    'form' => [
        'title' => 'Presentar solicitud',
        'what' => '¿De qué se trata?',
        'what_text' => 'Puede solicitar el acceso a los datos almacenados sobre usted, su rectificación o supresión, la limitación del tratamiento, la portabilidad de sus datos u oponerse al tratamiento.',
        'submit' => 'Enviar solicitud',
    ],

    'field' => [
        'type' => 'Tipo de solicitud',
        'full_name' => 'Nombre y apellidos',
        'email' => 'Dirección de correo electrónico para la respuesta',
        'reference' => 'Número de expediente, de cliente o de personal (opcional)',
        'message' => 'Su solicitud',
        'attachments' => 'Archivos adjuntos (opcional)',
        'attachments_hint' => 'Como máximo :max archivos, de hasta :size MB cada uno.',
        'honeypot' => 'No rellenar',
        'privacy_ack' => 'He leído la información sobre protección de datos y facilito mis datos según mi leal saber y entender.',
    ],

    'receipt' => [
        'title' => 'Solicitud recibida',
        'headline' => 'Su solicitud se ha recibido.',
        'number' => 'Expediente: :nr',
        'mail_sent' => 'Se ha enviado un acuse de recibo a la dirección indicada. El plazo legal de tramitación comienza con la recepción de hoy.',
        'back' => 'Volver al formulario',
    ],

    'confirmed' => [
        'title' => 'Dirección confirmada',
        'headline' => 'Gracias: su dirección de correo electrónico está confirmada.',
        'text' => 'La confirmación se ha anotado en el expediente :nr.',
        'no_deadline_effect' => 'El plazo de tramitación sigue corriendo desde la recepción de su solicitud; la confirmación no lo aplaza.',
    ],

    'mail' => [
        'subject' => 'Acuse de recibo de su solicitud de protección de datos :nr',
        'headline' => 'Su solicitud de protección de datos se ha recibido',
        'intro' => 'Con esta dirección de correo electrónico se ha presentado una solicitud de protección de datos con el expediente :nr.',
        'deadline' => 'El plazo legal de tramitación corre desde la recepción y finaliza el :date.',
        'confirm_button' => 'Confirmar dirección de correo electrónico',
        'confirm_note' => 'La confirmación acredita que esta dirección es accesible. No sustituye la verificación de su identidad: el responsable se pondrá en contacto con usted por separado. El clic no afecta al plazo.',
        'not_you' => 'Si no ha presentado usted esta solicitud, ignore este correo. No se facilita ninguna información sin verificación de identidad.',
    ],

    'subject' => [
        'email' => 'Correo electrónico: :value',
        'reference' => 'Expediente: :value',
    ],

    'internal' => [
        'from_portal' => 'Entrada por el portal',
        'portal_banner' => 'Esta solicitud llegó a través del portal público para interesados. Los datos de identidad son una declaración propia sin verificar.',
        'contact_email' => 'Dirección de respuesta',
        'email_confirmed' => 'confirmada el :date',
        'email_unconfirmed' => 'sin confirmar',
        'identity_required' => 'Antes de facilitar información debe verificarse y confirmarse la identidad (entrada por el portal).',
    ],

    'admin' => [
        'nav' => 'Portal de interesados',
        'title' => 'Gestionar el portal de interesados',
        'subtitle' => 'Configurar el formulario público para solicitudes de interesados.',
        'link' => 'Enlace público',
        'link_hint' => 'Publique este enlace en su política de privacidad. No puede deducirse del nombre de la organización.',
        'rotate' => 'Rotar el enlace',
        'rotate_confirm' => '¿Rotar realmente el enlace? Los enlaces ya publicados dejarán de ser válidos.',
        'not_created' => 'Todavía no se ha creado ningún portal de interesados. Guarde para crear uno con un enlace aleatorio.',
        'settings' => 'Ajustes',
        'visibility' => 'Visibilidad',
        'is_enabled' => 'Portal activo (accesible públicamente)',
        'allow_attachments' => 'Permitir archivos adjuntos',
        'presentation' => 'Presentación',
        'intro_text' => 'Texto introductorio (opcional)',
        'default_locale' => 'Idioma predeterminado (opcional, p. ej. es)',
        'saved' => 'Portal de interesados guardado.',
        'rotated' => 'El enlace del portal se ha rotado. Los enlaces ya publicados ya no son válidos.',
    ],
];
