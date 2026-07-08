<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ideas.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Mapas de ideas',
    ],
    'subtitle' => 'Mapas de ideas privados y compartidos — visibles solo para el propietario y las personas expresamente autorizadas.',
    'empty' => 'Aún no hay mapas de ideas.',
    'privacy_hint' => 'Los mapas nuevos son privados: visibles solo para usted hasta que los comparta expresamente con personas o equipos.',
    'confirm_delete' => '¿Mover el mapa a la papelera?',

    'action' => [
        'create' => 'Crear mapa',
        'edit' => 'Editar mapa',
        'archive' => 'Archivar',
        'unarchive' => 'Reactivar',
        'restore' => 'Restaurar',
    ],

    'col' => [
        'title' => 'Título',
        'description' => 'Descripción',
        'owner' => 'Propietario',
        'visibility' => 'Visibilidad',
        'nodes' => 'Nodos',
        'updated' => 'Modificado',
        'actions' => 'Acciones',
    ],

    'filter' => [
        'active' => 'Activos',
        'archived' => 'Archivados',
        'trashed' => 'Papelera',
    ],

    'visibility' => [
        'private' => 'Privado',
        'shared' => 'Compartido',
    ],

    'share_role' => [
        'viewer' => 'Lectura',
        'editor' => 'Edición',
    ],

    'color' => [
        'default' => 'Neutro',
        'primary' => 'Azul',
        'success' => 'Verde',
        'warning' => 'Amarillo',
        'error' => 'Rojo',
        'info' => 'Turquesa',
    ],

    'node_status' => [
        'open' => 'Abierta',
        'in_review' => 'En revisión',
        'decided' => 'Decidida',
        'rejected' => 'Rechazada',
        'done' => 'Realizada',
    ],

    'import' => [
        'action' => 'Importar',
        'title' => 'Importar mapa de ideas',
        'submit' => 'Importar',
        'file' => 'Archivo',
        'hint' => 'FreeMind/Freeplane (.mm) u OPML. Crea un mapa nuevo y privado.',
        'done' => 'Mapa importado.',
        'default_title' => 'Mapa importado',
        'error' => [
            'invalid' => 'El archivo no es un XML válido.',
            'unsupported' => 'Formato no compatible (solo FreeMind .mm y OPML).',
            'empty' => 'El archivo no contiene nodos.',
            'too_deep' => 'La estructura está anidada demasiado profundamente.',
            'too_large' => 'El mapa tiene demasiados nodos.',
        ],
    ],

    'legend' => [
        'context' => 'Contexto (opcional)',
        'map' => 'Mapa',
    ],

    'outline' => [
        'title' => 'Esquema',
        'empty' => 'Este mapa aún no tiene nodos.',
    ],

    'flash' => [
        'created' => 'Mapa creado.',
        'updated' => 'Mapa guardado.',
        'archived' => 'Mapa archivado.',
        'unarchived' => 'Mapa reactivado.',
        'deleted' => 'Mapa movido a la papelera.',
        'restored' => 'Mapa restaurado.',
        'owner_invalid' => 'Nuevo propietario no válido.',
        'ownership_transferred' => 'Propiedad transferida.',
        'share_granted' => 'Acceso concedido.',
        'share_revoked' => 'Acceso retirado.',
        'share_invalid' => 'Selección no válida (exactamente una persona o un equipo).',
    ],

    'share' => [
        'title' => 'Compartidos',
        'none' => 'Este mapa es privado — sin compartidos.',
        'user' => 'Persona',
        'team' => 'Equipo',
        'role' => 'Rol',
        'add' => 'Compartir',
        'revoke' => 'Retirar acceso',
        'hint' => 'Exactamente una persona O un equipo por cada acceso compartido. La pertenencia al equipo se comprueba al acceder.',
    ],

    'notification' => [
        'shared' => ':actor ha compartido un mapa de ideas con usted.',
    ],

    'export' => [
        'generated_at' => 'Creado el',
        'footer_note' => 'Exportación de la vista de esquema — las posiciones del lienzo se incluyen en la exportación JSON.',
    ],

    'context' => [
        'customer' => 'Cliente',
        'project' => 'Proyecto',
    ],

    'convert' => [
        'done' => 'Transferido:',
        'already' => 'Ya transferido:',
        'error' => [
            'module_disabled' => 'El módulo de destino no está activado.',
            'target_not_allowed' => 'Este destino no está permitido.',
        ],
    ],

    'editor' => [
        'outline' => 'Esquema',
        'canvas' => 'Mapa',
        'saving' => 'Guardando …',
        'undo_delete' => 'Deshacer eliminación',
        'keys_hint' => 'Intro: nuevo nodo · Tab: sangrar · Alt+↑/↓: mover · F2: renombrar',
        'conflict_title' => 'Cambio simultáneo detectado — su versión estaba obsoleta.',
        'conflict_take_server' => 'Usar la versión del servidor',
        'conflict_retry_mine' => 'Volver a aplicar mi cambio',
        'new_node' => 'Nueva idea',
        'convert_task' => 'A tarea',
        'convert_project' => 'A proyecto',
        'convert_knowledge' => 'A artículo de conocimiento',
        'confirm_delete_node' => '¿Mover el nodo y sus subnodos a la papelera?',
        'add_child' => 'Añadir subnodo',
        'rename' => 'Renombrar',
        'details' => 'Detalles',
        'move_up' => 'Subir',
        'move_down' => 'Bajar',
        'indent' => 'Sangrar',
        'outdent' => 'Quitar sangría',
        'delete' => 'Eliminar',
        'expand' => 'Expandir rama',
        'collapse' => 'Contraer rama',
        'zoom_in' => 'Ampliar',
        'zoom_out' => 'Reducir',
        'zoom_reset' => 'Restablecer zoom al 100 %',
        'fit' => 'Ajustar vista',
        'arrange' => 'Reorganizar',
        'arrange_hint' => 'Organizar automáticamente todos los nodos como árbol',
        'canvas_large' => 'Área de trabajo grande',
        'canvas_small' => 'Área de trabajo compacta',
        'canvas_keys_hint' => 'Tab: subnodo · Intro: nodo hermano · doble clic en el lienzo: nueva idea · arrastrar sobre un nodo: reubicar',
        'canvas_a11y_hint' => 'Edición accesible en la vista de esquema.',
        'export_svg' => 'Exportar como imagen SVG',
        'export_png' => 'Exportar como imagen PNG',
        'history' => 'Historial',
        'history_empty' => 'Aún no hay cambios.',
        'presence_suffix' => 'editando ahora',
        'note' => 'Nota',
        'color' => 'Color',
        'status' => 'Estado',
        'status_none' => '— sin estado',
    ],

    'error' => [
        'conflict' => 'El nodo se modificó entre tanto — revise el estado actual.',
        'cycle' => 'Un nodo no puede moverse debajo de uno de sus propios descendientes.',
        'root_immovable' => 'El nodo raíz no puede moverse ni eliminarse.',
        'foreign_node' => 'El nodo no pertenece a este mapa.',
    ],
];
