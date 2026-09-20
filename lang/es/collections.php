<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : collections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Sammlungen (MVP-809, Feature 155).
return [
    'title' => [
        'index' => 'Colecciones',
        'tree' => 'Árbol de colecciones',
    ],
    'subtitle' => 'Ordena juntos notas, mapas de ideas, artículos, documentos y contenidos de aprendizaje; un contenido puede estar en varias colecciones.',
    'action' => [
        'show_archived' => 'Mostrar archivadas',
        'hide_archived' => 'Ocultar archivadas',
        'create' => 'Crear colección',
        'create_child' => 'Crear subcolección',
        'edit' => 'Editar colección',
        'save' => 'Guardar',
        'archive' => 'Archivar',
        'restore' => 'Restaurar',
        'remove_item' => 'Quitar de la colección',
        'add_to_collection' => 'Añadir a colección',
        'add' => 'Añadir',
    ],
    'empty' => [
        'tree' => 'Aún no hay ninguna colección.',
        'selection' => 'Ninguna colección seleccionada.',
        'items' => 'Esta colección está vacía o solo contiene contenidos que no puedes ver.',
    ],
    'help' => [
        'intro' => 'Una colección ordena contenidos, no concede acceso: cada persona solo ve lo que puede ver de todos modos.',
        'add_from_detail' => 'Añade contenidos con «Añadir a colección» en su página de detalle.',
        'parent' => 'Como máximo :max niveles de profundidad.',
        'visibility' => 'Una colección privada solo la ve quien la creó.',
        'create_first' => 'Primero crea una colección en «Colecciones».',
        'multiple_membership' => 'Un contenido puede estar en varias colecciones sin copiarse.',
    ],
    'visibility' => [
        'organization' => 'Organización',
        'private' => 'Privada',
    ],
    'badge' => [
        'archived' => 'Archivada',
        'already_in' => 'ya incluido',
    ],
    'field' => [
        'type' => 'Tipo',
        'title' => 'Título',
        'added_by' => 'Añadido',
        'actions' => 'Acciones',
        'description' => 'Descripción',
        'parent' => 'Colección superior',
        'no_parent' => '— nivel superior —',
        'visibility' => 'Visibilidad',
        'subject' => 'Referencia',
        'customer' => 'Cliente',
        'collection' => 'Colección',
    ],
    'flash' => [
        'created' => 'Colección creada.',
        'updated' => 'Colección guardada.',
        'archived' => 'Colección archivada.',
        'restored' => 'Colección restaurada.',
        'item_added' => 'Añadido a «:collection».',
        'item_removed' => 'Quitado de la colección.',
        'items_added' => '{0} No se añadió ningún contenido.|{1} Un contenido añadido a «:collection».|[2,*] :count contenidos añadidos a «:collection».',
    ],
    'errors' => [
        'too_deep' => 'Las colecciones se pueden anidar como máximo :max niveles.',
        'cycle' => 'Una colección no puede estar debajo de sí misma ni de una de sus subcolecciones.',
        'type_not_allowed' => 'Este tipo de contenido no se puede añadir a una colección.',
        'item_not_found' => 'El contenido no existe o no es visible para ti.',
        'parent_invalid' => 'La colección superior elegida no existe (ya).',
    ],
    'type' => [
        'note' => 'Nota',
        'idea_map' => 'Mapa de ideas',
        'knowledge_article' => 'Artículo de conocimiento',
        'document' => 'Documento',
        'learning_course' => 'Curso',
        'learning_path' => 'Ruta de aprendizaje',
    ],
    'references' => [
        'title' => 'Referencias',
        'outgoing' => 'Remite a',
        'backlinks' => 'Mencionado en',
        'action' => [
            'create' => 'Añadir referencia',
            'remove' => 'Quitar referencia',
        ],
        'field' => [
            'target' => 'Destino',
            'search' => 'Buscar contenido …',
        ],
        'kind' => [
            'linked' => 'vinculado',
            'converted' => 'convertido',
            'mentioned' => 'mencionado',
        ],
        'empty' => 'Aún no hay referencias: ni desde aquí ni hacia aquí.',
        'empty_picker' => 'No hay más contenidos a los que puedas remitir.',
        'help' => 'Una referencia une dos contenidos sin dar acceso: solo ve el otro lado quien puede abrirlo.',
        'confirm_remove' => '¿Quitar esta referencia? Ambos contenidos se conservan.',
        'flash' => [
            'added' => 'Referencia a «:title» añadida.',
            'removed' => 'Referencia quitada.',
        ],
        'errors' => [
            'self' => 'Un contenido no puede remitir a sí mismo.',
            'not_removable' => 'Esta referencia la gestiona su módulo, no esta lista.',
        ],
    ],
    'hub' => [
        'title' => 'Conocimiento',
        'subtitle' => 'Notas, mapas de ideas, artículos de conocimiento, documentos y contenidos de aprendizaje en un solo lugar, ordenados por colecciones y etiquetas.',
        'search' => 'Buscar en títulos …',
        'all_types' => 'Todos los tipos',
        'all_customers' => 'Todos los clientes',
        'all_contents' => 'Todos los contenidos',
        'view_list' => 'Lista',
        'view_tiles' => 'Mosaico',
        'manage' => 'Gestionar colecciones',
        'tag_filter' => 'Filtro de etiqueta',
        'selected' => ':n contenidos seleccionados',
        'select_all' => 'Seleccionar todo',
        'select_item' => 'Seleccionar «:title»',
        'updated' => 'Modificado',
        'empty' => 'No se encontraron contenidos.',
        'empty_hint' => 'Relaja los filtros o elige otra colección.',
        'add_hits' => 'Añadir a colección',
    ],
    'import' => [
        'untitled' => 'Sin título',
        'source' => 'Origen',
        'target' => 'Importar como',
        'root_title' => 'Nombre de la colección',
        'root_title_hint' => 'Vacío: nombre de la carpeta o del bloc de notas. Se reutiliza una colección con el mismo nombre.',
        'rules' => 'Solo lectura y solo bajo demanda: los contenidos ya importados no cambian y no se escribe nada de vuelta. Como máximo :max contenidos nuevos por ejecución; otra ejecución importa el resto.',
        'origin' => 'Importado de :source el :date',
        'action' => [
            'start' => 'Iniciar importación',
        ],
        'obsidian' => [
            'title' => 'Importar de Obsidian',
            'action' => 'Importar Obsidian',
            'intro' => 'Lee una carpeta de Obsidian mediante una conexión de carpeta existente de la entrada de documentos (Nextcloud, OneDrive, Dropbox, Google Drive). Las subcarpetas pasan a ser colecciones, las etiquetas YAML y las #etiquetas se conservan y los [[wikilinks]] pasan a ser referencias.',
            'none' => 'No hay ninguna conexión de carpeta activa. Configura primero en Administración › Entrada de documentos en la nube una conexión que llegue a la carpeta del almacén de Obsidian.',
            'connection' => 'Conexión de carpeta',
            'vault_path' => 'Ruta del almacén',
            'vault_path_hint' => 'Relativa a la carpeta raíz de la conexión; vacío = toda la carpeta raíz. .obsidian/ y .trash/ quedan fuera.',
        ],
        'onenote' => [
            'title' => 'Importar de OneNote',
            'action' => 'Importar OneNote',
            'intro' => 'Importa un bloc de notas mediante la conexión de OneNote de solo lectura: los grupos de secciones y las secciones pasan a ser colecciones, cada página una nota o un artículo. El contenido de la página se importa como texto.',
            'none' => 'No se encontraron blocs de notas.',
            'error' => 'OneNote no está disponible ahora; revisa la conexión en el panel de Microsoft 365.',
            'notebook' => 'Bloc de notas',
        ],
        'flash' => [
            'done' => 'Importado: :created nuevos, :skipped ya existentes, :collections colecciones creadas, :links referencias añadidas.',
            'limited' => 'Se alcanzó el límite de :max contenidos nuevos; otra ejecución importa el resto.',
            'failed' => 'La importación falló. Los contenidos ya importados se conservan; otra ejecución continúa.',
            'source_unavailable' => 'El origen no está (o ya no está) disponible.',
            'notebook_invalid' => 'El bloc de notas elegido no está (o ya no está) disponible.',
        ],
    ],
];
