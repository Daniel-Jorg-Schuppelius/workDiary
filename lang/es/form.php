<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : form.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'templates' => 'Plantillas de formulario',
        'template' => 'Plantilla',
        'submissions' => 'Formularios',
        'submission' => 'Formulario cumplimentado',
        'values' => 'Entradas',
        'panel' => 'Formularios',
    ],

    'subtitle' => [
        'templates' => 'Mantener formularios configurables (actas, listas de control) sin código.',
        'submissions' => 'Formularios cumplimentados — a prueba de versiones mediante la instantánea de la definición de campos.',
    ],

    'field' => [
        'name' => 'Nombre',
        'description' => 'Descripción',
        'status' => 'Estado',
        'fields' => 'Campos',
        'submissions' => 'Cumplimentados',
        'creator' => 'Creado por',
        'template' => 'Plantilla',
        'subject' => 'Referencia',
        'submitted_by' => 'Cumplimentado por',
        'submitted_at' => 'Cumplimentado el',
        'field_label' => 'Etiqueta del campo',
        'field_type' => 'Tipo de campo',
        'field_required' => 'Obligatorio',
        'field_options' => 'Opciones',
        'field_help' => 'Texto de ayuda',
        'field_unit' => 'Unidad',
    ],

    'action' => [
        'create_template' => 'Crear plantilla',
        'edit' => 'Editar',
        'save' => 'Guardar',
        'activate' => 'Activar',
        'archive' => 'Archivar',
        'delete' => 'Eliminar',
        'add_field' => 'Añadir campo',
        'remove_field' => 'Quitar campo',
        'fill' => 'Rellenar formulario',
        'submit' => 'Enviar',
        'show' => 'Ver',
        'print' => 'Imprimir',
        'download_pdf' => 'Descargar PDF',
        'clear_signature' => 'Borrar firma',
        'back' => 'Volver',
    ],

    'filter' => [
        'all' => 'Todos',
        'search' => 'Búsqueda',
        'search_placeholder' => 'Buscar nombre de plantilla',
        'period' => 'Período',
    ],

    'hint' => [
        'options' => 'Separadas por comas, p. ej. bueno, regular, malo',
        'unit' => 'p. ej. kWh, °C, unidades',
    ],

    'subject_kind' => [
        'diary' => 'Encargo',
        'customer' => 'Cliente',
        'asset' => 'Activo',
        'project' => 'Proyecto',
    ],

    'value' => [
        'yes' => 'Sí',
        'no' => 'No',
        'signed' => 'Firmado',
    ],

    'condition' => [
        'legend' => 'Visible cuando',
        'always' => '— siempre visible —',
        'value_placeholder' => 'Valor de comparación',
        'op' => [
            'eq' => 'igual a',
            'ne' => 'distinto de',
            'in' => 'uno de (coma)',
            'filled' => 'rellenado',
        ],
    ],

    'validation' => [
        'invalid_row' => 'La definición del campo en la fila :row no es válida.',
        'label_required' => 'El campo :row necesita una etiqueta (máx. 160 caracteres).',
        'unknown_type' => 'El campo :row tiene un tipo desconocido.',
        'invalid_key' => 'La clave de campo «:key» no es válida (minúsculas, dígitos, guiones bajos).',
        'duplicate_key' => 'La clave de campo «:key» está duplicada.',
        'select_needs_options' => 'El campo de selección «:label» necesita al menos una opción.',
        'fields_required' => 'La plantilla necesita al menos un campo.',
        'too_many_fields' => 'Como máximo :max campos por plantilla.',
        'template_not_active' => 'Esta plantilla no está activa y no se puede rellenar.',
        'condition_unknown_field' => 'La condición del campo «:label» hace referencia a un campo desconocido «:field».',
        'condition_cycle' => 'Las condiciones forman un ciclo (el campo «:field» depende indirectamente de sí mismo).',
    ],

    'flash' => [
        'template_created' => 'La plantilla se ha creado.',
        'template_updated' => 'La plantilla se ha actualizado.',
        'template_activated' => 'La plantilla se ha activado.',
        'template_archived' => 'La plantilla se ha archivado.',
        'template_deleted' => 'La plantilla se ha eliminado.',
        'submitted' => 'El formulario se ha guardado.',
    ],

    'empty_templates_title' => 'No se encontraron plantillas',
    'empty_templates' => 'Todavía no hay plantillas de formulario.',
    'empty_submissions_title' => 'No se encontraron formularios',
    'empty_submissions' => 'Todavía no hay formularios cumplimentados.',
    'empty_filtered' => 'No se encontraron entradas para los filtros actuales.',
    'empty_panel' => 'Todavía no se han cumplimentado formularios para este registro.',
    'confirm_archive' => '¿Archivar realmente esta plantilla? Desaparecerá de la selección de cumplimentación.',
    'confirm_delete' => '¿Eliminar realmente esta plantilla? Los formularios cumplimentados se conservan.',
];
