<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : external.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'party' => [
        'subcontractor' => 'Subcontratista',
        'inspector' => 'Inspector',
        'expert' => 'Perito',
        'other' => 'Otro',
    ],
    'ability' => [
        'view' => 'Ver',
        'comment' => 'Comentar',
        'upload' => 'Subir archivo',
        'confirm' => 'Confirmar',
    ],
    'status' => [
        'invited' => 'Invitado',
        'accessed' => 'Accedido',
        'expired' => 'Caducado',
        'revoked' => 'Revocado',
    ],
    'subject' => [
        'order' => 'Encargo',
        'generic' => 'Elemento',
    ],
    'panel' => [
        'title' => 'Participantes externos',
        'invite' => 'Invitar',
        'empty' => 'Aún no se ha invitado a ningún participante externo.',
        'link_once' => 'Copia este enlace una sola vez y envíalo al participante externo — no se volverá a mostrar.',
    ],
    'col' => [
        'name' => 'Nombre',
        'party' => 'Tipo',
        'abilities' => 'Derechos',
        'status' => 'Estado',
        'expires' => 'Válido hasta',
    ],
    'group' => [
        'contact' => 'Contacto',
        'abilities' => 'Acciones permitidas',
        'validity' => 'Validez',
    ],
    'field' => [
        'name' => 'Nombre',
        'email' => 'Correo electrónico (opcional)',
        'role' => 'Rol',
        'party' => 'Tipo',
        'ttl_days' => 'Validez (días)',
    ],
    'hint' => [
        'role' => 'p. ej. Electricista, inspector TÜV',
        'abilities' => 'La consulta siempre está permitida. Las acciones adicionales se aplican estrictamente en el servidor.',
        'ttl_days' => 'De 1 a 180 días. Después el acceso caduca automáticamente.',
    ],
    'invite' => [
        'title' => 'Invitar a un participante externo',
        'eyebrow' => 'Participantes externos',
        'submit' => 'Invitar y generar enlace',
        'once_hint' => 'El enlace de acceso se muestra una sola vez tras crearlo — solo se almacena el hash.',
    ],
    'revoke' => [
        'action' => 'Revocar',
        'title' => 'Revocar acceso',
        'message' => 'El acceso externo se bloqueará de inmediato. ¿Continuar?',
        'confirm' => 'Revocar',
    ],
    'flash' => [
        'invited' => 'Participante externo «:name» invitado.',
        'revoked' => 'Acceso externo revocado.',
    ],
    'public' => [
        'title' => 'Acceso externo',
        'hello' => 'Hola :name',
        'expires_note' => 'Este acceso es válido hasta el :date.',
        'view_only' => 'Este acceso está limitado a la consulta.',
        'comment_heading' => 'Dejar un comentario',
        'comment_placeholder' => 'Su observación …',
        'comment_submit' => 'Enviar comentario',
        'comment_saved' => 'Comentario guardado.',
        'upload_heading' => 'Subir archivo o foto',
        'upload_hint' => 'Permitido: JPG, PNG, GIF, WEBP, PDF (máx. 25 MB).',
        'upload_submit' => 'Subir',
        'upload_saved' => 'Archivo subido.',
        'upload_rejected' => 'Tipo de archivo no permitido.',
        'confirm_heading' => 'Confirmar / Aceptar',
        'confirm_note_placeholder' => 'Observación opcional para la confirmación …',
        'confirm_accept' => 'Confirmo la exactitud de los datos.',
        'confirm_submit' => 'Confirmar',
        'confirmed' => 'Confirmación guardada.',
    ],
];
