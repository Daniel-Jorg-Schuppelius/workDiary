<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : recipes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Recetas (MVP-455): gestión de recetas neutra + extensión de catering.
return [
    'title' => [
        'materials' => 'Receta / necesidades de material',
        'party' => 'Catering: rendimiento base y porciones',
        'allergen_overrides' => 'Desviaciones de alérgenos (con justificación)',
        'allergens' => 'Alérgenos',
        'plan' => 'Escalado y costes planificados',
    ],
    'hint' => [
        'version' => 'Versión :version',
        'readonly' => 'publicado — estado inmutable',
        'materials' => 'Cantidades fijas, cantidades por unidad/porción o proporciones de mezcla; las herramientas quedan separadas del consumo. Las posiciones solo se editan en borradores.',
        'ratio_input' => 'En «proporción», el valor indica la parte de la cantidad objetivo (suma de partes = cantidad total).',
        'party' => 'El rendimiento base documenta cuántas porciones produce la preparación estándar; las cantidades por unidad se registran por porción.',
    ],
    'empty' => [
        'no_version' => 'Aún no hay versión — cree primero un borrador.',
        'no_materials' => 'Aún no hay posiciones.',
    ],
    'field' => [
        'position' => 'Pos.',
        'article' => 'Artículo/ingrediente',
        'article_placeholder' => '… seleccionar artículo',
        'kind' => 'Tipo de cantidad',
        'quantity' => 'Cantidad',
        'quantity_or_ratio' => 'Cantidad / parte',
        'unit' => 'Unidad',
        'waste' => 'Merma %',
        'tool' => 'Herramienta',
        'tool_yes' => 'Herramienta',
        'actions' => 'Acciones',
        'base_portions' => 'Rendimiento base (porciones)',
        'base_yield' => 'Cantidad producida',
        'yield_unit' => 'Unidad producida',
        'allergen_added' => 'Declarar adicionalmente',
        'allergen_removed' => 'No declarar',
        'override_reason' => 'Justificación de la desviación',
        'portions' => 'Porciones',
        'demand' => 'Necesidad',
        'cost' => 'Costes planificados',
    ],
    'kind' => [
        'fixed' => 'fijo por preparación',
        'per_unit' => 'por porción/unidad',
        'ratio' => 'proporción',
    ],
    'action' => [
        'add' => 'Añadir posición',
        'remove' => 'Quitar',
        'save_profile' => 'Guardar perfil',
        'save_allergens' => 'Guardar alérgenos',
        'scale' => 'Escalar',
        'back' => 'Volver al resumen',
    ],
    'allergens' => [
        'none' => 'Sin alérgenos declarados.',
        'unresolved_heading' => 'Ingredientes sin asignación de alérgenos',
    ],
    'plan' => [
        'total' => 'Total',
        'per_portion' => 'por porción',
    ],
    'flash' => [
        'material_saved' => 'Posición guardada.',
        'material_removed' => 'Posición eliminada.',
        'profile_saved' => 'Perfil de receta guardado.',
        'allergens_saved' => 'Alérgenos del ingrediente guardados.',
        'menu_saved' => 'Menú guardado.',
    ],
    'error' => [
        'published_immutable' => 'Los estados publicados son inmutables — cree una nueva versión.',
        'override_reason_required' => 'Las desviaciones de alérgenos requieren una justificación.',
        'ratio_required' => 'Para una proporción indique un valor mayor que 0.',
        'allergens_unresolved' => 'Publicación bloqueada: ingredientes sin asignación de alérgenos (:articles). Asigne alérgenos o registre una desviación justificada.',
    ],
    'costs' => [
        'unit_unmapped' => ':article: la unidad «:unit» no se puede convertir a la unidad base — costes incompletos.',
        'price_missing' => ':article: sin precio de compra registrado — costes incompletos.',
    ],
    'menu' => [
        'title' => 'Planificación de menús',
        'intro' => 'Menús y bufés a partir de recetas publicadas — el número de invitados escala la necesidad agregada.',
        'empty' => 'Aún no hay menús.',
        'no_date' => 'sin fecha',
        'no_dishes' => 'Aún no hay platos en el menú.',
        'not_published' => 'sin estado publicado',
        'dishes_heading' => 'Platos',
        'aggregate_heading' => 'Necesidad de material agregada',
        'missing_published' => 'No considerado (sin estado publicado): :dishes',
        'no_materials' => 'Sin necesidades — añada platos con recetas publicadas.',
        'field' => [
            'name' => 'Nombre',
            'event_date' => 'Fecha',
            'guest_count' => 'N.º de invitados',
            'dishes' => 'Platos',
            'dish' => 'Plato',
            'dish_placeholder' => '… seleccionar plato',
            'portions_per_guest' => 'Porciones por invitado',
            'portions_total' => 'Porciones en total',
            'version' => 'Estado de receta',
        ],
        'action' => [
            'create' => 'Crear menú',
            'open' => 'Abrir',
            'add_dish' => 'Añadir plato',
        ],
    ],
];
