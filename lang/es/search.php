<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : search.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Búsqueda',
    'subtitle' => 'Encontrar actividades, clientes y objetos: ¿qué se hizo, cuándo y para qué cliente?',
    'placeholder' => 'p. ej. smtp exchange, "smtp relay", -test',

    'group' => [
        'activities' => 'Actividades',
    ],

    'source' => [
        'time_entry' => 'Registro de tiempo',
        'diary_entry' => 'Encargo',
        'timesheet' => 'Parte de horas',
        'service_ticket' => 'Ticket',
        'protocol' => 'Acta',
        'open_issue' => 'Punto abierto',
        'communication_note' => 'Nota de comunicación',
        'knowledge_article' => 'Artículo de conocimiento',
        'remote_session' => 'Asistencia remota (sin asignar)',
        'learning_course' => 'Curso de aprendizaje',
        'document' => 'Documento',
    ],

    'field' => [
        'query' => 'Término de búsqueda',
        'type' => 'Fuente',
        'all_types' => 'Todas las fuentes',
        'person' => 'Persona',
        'all_persons' => 'Todas las personas',
        'customer' => 'Cliente',
        'all_customers' => 'Todos los clientes',
        'foreign_customer' => 'Cliente final',
        'all_foreign_customers' => 'Todos los clientes finales',
        'sort' => 'Orden',
        'sort_relevance' => 'Mejores resultados primero',
        'sort_date' => 'Más recientes primero',
        'similar' => 'Grafías similares',
        'collection' => 'Colección',
        'all_collections' => 'Todas las colecciones',
    ],

    'filter' => [
        'project' => 'Proyecto: :name',
        'remove' => 'Quitar filtro',
        'tag' => 'Etiqueta: :name',
        'tag_without_hits' => 'Filtro de etiqueta',
    ],

    'notice' => [
        'corrections' => '«:word» no aparece; también se buscó: :candidates.',
        'synonyms' => 'También se buscó: :list',
        'ignored' => 'No considerado: :words',
    ],

    'aggregate' => [
        'title' => 'Clientes y clientes finales',
        'without_customer' => 'sin cliente',
        'hits' => ':count resultado|:count resultados',
    ],

    'types' => [
        'title' => 'Fuentes',
    ],

    'facets' => [
        'tags' => 'Etiquetas en los resultados',
    ],

    'hits' => [
        'title' => 'Actividades',
        'open' => 'Abrir',
    ],

    'column' => [
        'date' => 'Fecha',
        'activity' => 'Actividad',
        'customer' => 'Cliente › Cliente final / Proyecto',
        'person' => 'Persona',
        'duration' => 'Duración',
    ],

    'empty' => [
        'start' => '¿Qué está buscando?',
        'start_hint' => 'Bastan palabras clave, p. ej. «smtp exchange». Todas las palabras deben aparecer: en el registro, el proyecto o el cliente.',
        'none' => 'Sin resultados.',
        'none_hint' => 'Pruebe con menos palabras o active «Grafías similares».',
    ],

    'entities' => [
        'title' => 'Datos maestros y objetos',
        'more' => 'Todos los resultados de este grupo →',
        'back' => '← Volver a todos los resultados',
    ],

    'box' => [
        'title' => 'Buscar en actividades',
        'label' => 'Término de búsqueda',
        'placeholder' => 'Palabras clave, p. ej. smtp exchange',
        'placeholder_customer' => '¿Qué se hizo para este cliente o sus clientes finales?',
        'placeholder_foreign_customer' => '¿Qué se hizo en este cliente final?',
        'hint_customer' => 'Busca en tiempos, encargos, partes de horas, tickets, actas y notas del cliente y de todos sus clientes finales. Sin término de búsqueda aparecen las actividades más recientes.',
        'hint_foreign_customer' => 'Busca en todas las actividades de este cliente final. Sin término de búsqueda aparecen las más recientes.',
        'submit' => 'Buscar',
        'project_action' => 'Buscar en actividades',
    ],

    'open' => [
        'range_set' => 'Periodo fijado en :date para que la entrada aparezca en la lista.',
    ],

    'palette' => [
        'placeholder' => 'Buscar actividades, clientes, proyectos, objetos …',
    ],

    'ai' => [
        'action' => 'Respuesta IA',
        'source_hint' => 'Búsqueda «:query» · :count resultados',
        'customer_alias' => 'Cliente :letter',
        'no_hits' => 'No hay resultados que resumir.',
    ],

    'synonyms' => [
        'title' => 'Sinónimos de búsqueda',
        'subtitle' => 'Términos con el mismo significado: quien busca uno encuentra también los demás.',
        'notice' => 'Ejemplo: si «smtp, mailrelay, sendeconnector» forman un grupo, la búsqueda de «smtp» encuentra también registros que solo mencionan «Sendeconnector». Se aplica a toda la organización.',
        'legend' => 'Grupo de sinónimos',
        'terms_help' => 'Un término por línea (o separados por comas), al menos dos y como máximo 20. Se admiten términos de varias palabras como «send connector».',
        'empty' => 'Todavía no hay grupos de sinónimos.',
        'delete_confirm' => '¿Eliminar este grupo de sinónimos? La búsqueda dejará de encontrar los términos entre sí.',
        'field' => [
            'terms' => 'Términos',
            'creator' => 'Creado por',
            'active' => 'Activo',
            'enabled_yes' => 'Sí',
            'enabled_no' => 'No',
        ],
        'action' => [
            'new' => 'Crear grupo',
            'edit' => 'Editar grupo',
            'submit' => 'Guardar',
            'activate' => 'Activar',
            'deactivate' => 'Desactivar',
            'delete' => 'Eliminar',
            'preset_it' => 'Importar plantilla TI',
        ],
        'flash' => [
            'saved' => 'Grupo de sinónimos creado.',
            'updated' => 'Grupo de sinónimos actualizado.',
            'deleted' => 'Grupo de sinónimos eliminado.',
            'activated' => 'Grupo de sinónimos activado.',
            'deactivated' => 'Grupo de sinónimos desactivado.',
            'preset_imported' => '{0} Todos los grupos de la plantilla ya existen.|{1} :count grupo importado de la plantilla.|[2,*] :count grupos importados de la plantilla.',
        ],
        'validation' => [
            'min_terms' => 'Un grupo necesita al menos dos términos distintos.',
            'max_terms' => 'Como máximo :max términos por grupo.',
            'term_length' => 'Un término puede tener como máximo :max caracteres.',
        ],
    ],
];
