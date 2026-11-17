<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : mail_file.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Mail-Dateien ablegen und ansehen (Feature 204, MVP-1652/1653).
 */

return [
    'title' => 'Correo',
    'title_with_subject' => 'Correo: :subject',
    'no_subject' => '(sin asunto)',
    'belongs_to' => 'Pertenece a',
    'format' => [
        'eml' => 'Archivo de correo (.eml)',
        'msg' => 'Mensaje de Outlook (.msg)',
    ],
    'field' => [
        'from' => 'De',
        'to' => 'Para',
        'cc' => 'CC',
        'bcc' => 'CCO',
        'date' => 'Fecha',
        'subject' => 'Asunto',
        'message_id' => 'Message-ID',
        'file' => 'Archivo',
    ],
    'text' => [
        'heading' => 'Texto',
        'from_html' => 'El correo solo tiene una parte HTML; el texto se ha obtenido de ella.',
        'truncated' => 'El texto es muy largo y se muestra abreviado.',
        'rtf_only' => 'El correo solo contiene texto con formato de Outlook (RTF), que workDiary no muestra. Descargue el archivo para abrirlo en Outlook.',
        'empty' => 'El correo no contiene texto.',
    ],
    'html' => [
        'show' => 'Mostrar presentación original',
        'hide' => 'Ocultar presentación original',
        'heading' => 'Presentación original',
        'hint' => 'Se muestra aislado: sin scripts, sin formularios y sin imágenes de internet.',
        'frame_title' => 'Presentación original del correo',
        'frame_unavailable' => 'La presentación original es demasiado extensa para mostrarla de forma aislada.',
    ],
    'attachments' => [
        'heading' => 'Adjuntos',
        'none' => 'El correo no tiene adjuntos.',
        'inline' => 'incrustado en el texto',
        'embedded' => 'Correo incrustado',
        'open' => 'Abrir',
        'download' => 'Descargar',
        'adopt' => 'Guardar como archivo',
        'adopt_hint' => 'Guarda una copia del adjunto como archivo propio en el mismo registro.',
        'preview_heading' => 'Vista previa: :name',
        'preview_close' => 'Cerrar vista previa',
        'unchecked' => 'Tipo de archivo no comprobado: se descarga como .bin.',
    ],
    'download_original' => 'Descargar archivo de correo',
    'unreadable' => [
        'title' => 'No se puede mostrar el correo',
        'hint' => 'El archivo sigue guardado sin cambios y se puede descargar.',
        'pending' => 'El correo se está leyendo. Vuelva a cargar la página en un momento.',
    ],
    'failure' => [
        'missing' => 'El archivo de correo ya no existe.',
        'too_large' => 'El archivo de correo es más grande de lo permitido para adjuntos.',
        'too_many_attachments' => 'El correo tiene más adjuntos de los que lee workDiary.',
        'attachments_too_large' => 'Los adjuntos del correo son demasiado grandes en conjunto.',
        'too_deep' => 'El correo contiene correos incrustados unos en otros a demasiada profundidad.',
        'too_complex' => 'El correo tiene una estructura demasiado anidada para leerlo de forma segura.',
        'malformed' => 'El archivo está dañado o no es un correo.',
        'aborted' => 'No se pudo leer el correo a tiempo.',
    ],
    'error' => [
        'adopt_too_large' => ':name supera :size y no se puede guardar.',
    ],
    'flash' => [
        'adopted' => '«:name» se ha guardado como archivo.',
    ],
    'section' => [
        'view' => 'Ver correo',
        'hint' => 'Máx. :mb MB. Permitido: :formats.',
        'drop' => 'Arrastre aquí un archivo, por ejemplo un correo de Outlook (.msg) o un archivo .eml.',
        'drop_single' => 'Suelte un archivo cada vez.',
    ],
    // Demo-Daten (erfunden): Rückfrage am Hauptauftrag.
    'demo' => [
        'subject' => 'Consulta sobre el presupuesto – medidas de las ventanas',
        'body' => "Buenos días:\n\n¿Podrían confirmarnos de nuevo las medidas de las ventanas de la planta baja? El pedido debe salir esta semana.\n\nUn saludo\nErika Beispiel",
        'sender' => 'Erika Beispiel (Beispiel GmbH)',
        'attachment' => 'Nota: ventana planta baja, ancho 1,20 m, alto 1,40 m.',
    ],
];
