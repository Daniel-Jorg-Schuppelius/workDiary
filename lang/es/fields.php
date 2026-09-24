<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : fields.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Feldschema-Baustein (MVP-866): Validierung der Definitionen, Anzeigewerte.
return [
    'validation' => [
        'invalid_row' => 'La definición del campo en la fila :row no es válida.',
        'label_required' => 'El campo :row necesita una etiqueta (máx. 160 caracteres).',
        'unknown_type' => 'El campo :row tiene un tipo desconocido.',
        'invalid_key' => 'La clave de campo «:key» no es válida (minúsculas, dígitos, guiones bajos).',
        'duplicate_key' => 'La clave de campo «:key» está duplicada.',
        'select_needs_options' => 'El campo de selección «:label» necesita al menos una opción.',
        'fields_required' => 'Se necesita al menos un campo.',
        'too_many_fields' => 'Como máximo :max campos.',
        'range_invalid' => 'Campo «:label»: el mínimo no puede superar el máximo.',
        'condition_unknown_field' => 'La condición del campo «:label» hace referencia a un campo desconocido «:field».',
        'condition_cycle' => 'Las condiciones forman un ciclo (el campo «:field» depende indirectamente de sí mismo).',
    ],
    'value' => [
        'yes' => 'Sí',
        'no' => 'No',
        'signed' => 'Firmado',
        'attachments' => '{1} :count adjunto|[2,*] :count adjuntos',
    ],
    'action' => [
        'clear_signature' => 'Borrar firma',
    ],
    'custom' => [
        'listed' => 'Mostrar en la lista',
        'title' => 'Campos propios',
        'legend' => 'Campos adicionales',
        'subject' => 'Objeto',
        'fields' => 'Campos',
        'status' => 'Estado',
        'active' => 'Activo',
        'inactive' => 'Desactivado',
        'none' => 'Aún no hay campos definidos.',
        'edit' => 'Editar campos',
        'save' => 'Guardar',
        'activate' => 'Activar',
        'deactivate' => 'Desactivar',
        'values_count' => ':count registros con valores',
        'version' => 'Versión del esquema :version',
        'saved' => 'Campos propios guardados.',
        'toggled' => 'Estado cambiado.',
        'intro' => 'Una lista de campos por objeto; los campos aparecen en el formulario, en la página de detalle y en las exportaciones. Desactive en lugar de borrar cuando ya existan valores.',
        'validation' => [
            'too_many_listed' => 'Como máximo :max campos pueden aparecer en la lista.',
            'unknown_subject' => 'Este objeto no tiene campos propios.',
            'type_not_allowed' => 'Campo «:label»: aquí no son posibles campos de archivo, foto ni firma.',
        ],
    ],
];
