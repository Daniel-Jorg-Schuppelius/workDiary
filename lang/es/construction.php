<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : construction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Escritos VOB/B',
    'subtitle' => 'Avisos de obstrucción y comunicaciones de reparos con acuse de recibo.',
    'empty' => 'No hay escritos registrados.',
    'dialog_hint' => 'Los hechos son el núcleo del escrito: concisos, verificables y fechados. Las referencias legales son texto — WorkDiary no presta asesoramiento jurídico.',
    'disclaimer' => 'Las referencias legales son bloques de texto y no asesoramiento jurídico. Si un plazo corre o se prolonga el tiempo de ejecución lo deciden las partes contratantes.',

    'kind' => [
        'obstruction' => 'Aviso de obstrucción',
        'concern' => 'Comunicación de reparos',
    ],

    'legal' => [
        'obstruction' => '§ 6 apdo. 1 VOB/B',
        'concern' => '§ 4 apdo. 3 VOB/B',
    ],

    'status' => [
        'draft' => 'Borrador',
        'sent' => 'Enviado',
        'acknowledged' => 'Recepción confirmada',
    ],

    'column' => [
        'number' => 'Número',
        'kind' => 'Tipo',
        'subject' => 'Asunto',
        'project' => 'Obra',
        'occurred_on' => 'Fecha',
        'status' => 'Estado',
    ],

    'filter' => [
        'kind' => 'Tipo',
        'status' => 'Estado',
    ],

    'field' => [
        'site' => 'Lugar de actuación',
        'customer' => 'Comitente',
        'diary_entry' => 'Origen (entrada del diario)',
        'recipient_name' => 'Destinatario',
        'recipient_email' => 'Correo del destinatario',
        'facts' => 'Hechos',
        'facts_hint' => '¿Qué obstaculiza exactamente los trabajos o fundamenta el reparo? Causa, prestación afectada, momento.',
        'impact_schedule' => 'Repercusión en el plazo de obra',
        'impact_cost' => 'Repercusión en los costes',
        'claims_time_extension' => 'Prórroga de plazo solicitada',
        'claims_time_extension_hint' => 'Solo una anotación en el escrito — WorkDiary no desplaza ningún plazo por ello.',
        'legal_reference' => 'Referencia legal',
        'legal_reference_hint' => 'Aparece como texto en el escrito.',
        'acknowledged_note' => 'Anotación sobre la recepción',
    ],

    'section' => [
        'context' => 'Asignación',
        'weather' => 'Meteorología del día en cuestión',
        'delivery' => 'Acuse de recibo',
        'acknowledge' => 'Confirmación de recepción',
    ],

    'action' => [
        'edit' => 'Editar',
        'pdf' => 'PDF',
        'send' => 'Enviar',
        'acknowledge' => 'Confirmar recepción',
    ],

    'badge' => [
        'time_extension' => 'Prórroga de plazo solicitada',
    ],

    'note' => [
        'time_extension' => 'Anotación: se ha solicitado una prórroga de plazo. Los plazos en WorkDiary permanecen sin cambios — una prórroga solo surte efecto cuando las partes la acuerdan y se registra aquí.',
        'time_extension_short' => 'Una prórroga solicitada es una anotación; WorkDiary no desplaza los plazos automáticamente.',
    ],

    'delivery' => [
        'none' => 'Todavía no se ha registrado ningún acuse de recibo.',
        'method' => 'Vía de entrega',
        'method_registered_mail' => 'Carta certificada',
        'method_courier' => 'Mensajero',
        'method_handover' => 'Entrega en mano',
        'method_fax' => 'Fax',
        'method_portal' => 'Portal de licitación/obra',
        'delivered_at' => 'Entregado el',
        'recipient' => 'Destinatario',
        'reference' => 'Número de recibo/envío',
        'record' => 'Registrar entrega',
    ],

    'mail' => [
        'title' => 'Enviar :label :nr por correo electrónico',
    ],

    'pdf' => [
        'number' => 'Número',
        'subject' => 'Asunto',
        'occurred_on' => 'Fecha',
        'project' => 'Obra',
        'site' => 'Lugar de actuación',
        'legal_reference' => 'Referencia legal',
        'facts' => 'Hechos',
        'impact_schedule' => 'Repercusión en el plazo de obra',
        'impact_cost' => 'Repercusión en los costes',
        'weather' => 'Meteorología del día en cuestión',
        'weather_values' => 'Valores medidos',
        'weather_source' => 'Fuente',
        'time_extension' => 'Prórroga de plazo solicitada',
        'time_extension_text' => 'Solicitamos una prórroga del plazo de ejecución equivalente a la duración de la obstrucción.',
        'disclaimer' => 'Este escrito cita las disposiciones aplicables como bloque de texto. No sustituye a una revisión jurídica.',
    ],

    'error' => [
        'frozen' => 'Un escrito enviado queda fijado y ya no puede modificarse.',
    ],

    'created' => 'Escrito creado.',
    'updated' => 'Escrito guardado.',
    'deleted' => 'Borrador eliminado.',
    'delivery_recorded' => 'Acuse de recibo registrado.',
    'acknowledged' => 'Recepción confirmada.',
];
