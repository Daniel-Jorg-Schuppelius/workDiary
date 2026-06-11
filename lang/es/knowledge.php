<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : knowledge.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Base de conocimientos',
        'links' => 'Historial de problemas',
        'linked' => 'Artículos vinculados',
        'suggestions' => 'Sugerencias',
    ],

    'subtitle' => 'Problemas conocidos, pasos de solución y notas internas del día a día.',

    'field' => [
        'title' => 'Título',
        'category' => 'Categoría',
        'tags' => 'Etiquetas',
        'status' => 'Estado',
        'problem' => 'Descripción del problema',
        'solution' => 'Pasos de solución',
        'helpful' => 'Valoración',
        'creator' => 'Creado por',
        'published_at' => 'Publicado el',
        'updated_at' => 'Última modificación',
    ],

    'action' => [
        'create' => 'Crear artículo',
        'create_from_subject' => 'Crear artículo a partir de esto',
        'edit' => 'Editar',
        'save' => 'Guardar',
        'show' => 'Ver',
        'publish' => 'Publicar',
        'archive' => 'Archivar',
        'delete' => 'Eliminar',
        'link' => 'Vincular',
        'unlink' => 'Quitar vínculo',
        'back' => 'Volver',
    ],

    'filter' => [
        'all' => 'Todos',
        'search' => 'Búsqueda',
        'search_placeholder' => 'Buscar en título, problema o solución',
        'sort' => 'Orden',
        'sort_newest' => 'Más recientes primero',
        'sort_helpful' => 'Más útiles primero',
    ],

    'feedback' => [
        'title' => '¿Te ha resultado útil este artículo?',
        'helpful' => 'Ha ayudado',
        'not_helpful' => 'No ha ayudado',
        'already_voted' => 'Ya has votado — votar de nuevo cambia tu voto.',
    ],

    'link_kind' => [
        'diary' => 'Orden de trabajo',
        'asset' => 'Activo',
        'customer' => 'Cliente',
        'protocol' => 'Protocolo',
    ],

    'hint' => [
        'category' => 'p. ej. impresora, red, calefacción …',
        'tags' => 'Separadas por comas, p. ej. firmware, modelo-x',
        'problem' => '¿Qué síntoma/problema aparece?',
        'solution' => '¿Qué pasos llevan a la solución?',
    ],

    'flash' => [
        'created' => 'Artículo creado.',
        'updated' => 'Artículo actualizado.',
        'published' => 'Artículo publicado.',
        'archived' => 'Artículo archivado.',
        'deleted' => 'Artículo eliminado.',
        'feedback_saved' => 'Gracias por tu valoración.',
        'linked' => 'Artículo vinculado.',
        'unlinked' => 'Vínculo eliminado.',
    ],

    'empty' => 'Aún no hay artículos de conocimiento.',
    'empty_title' => 'No se encontraron artículos',
    'empty_filtered' => 'Ningún artículo coincide con los filtros actuales.',
    'empty_links' => 'Aún no hay vínculos.',
    'empty_context' => 'No hay artículos vinculados ni sugerencias coincidentes.',
    'confirm_archive' => '¿Archivar realmente este artículo? Desaparecerá de la búsqueda y de las sugerencias.',
    'confirm_delete' => '¿Eliminar realmente este artículo?',
    'confirm_unlink' => '¿Quitar realmente este vínculo?',
];
