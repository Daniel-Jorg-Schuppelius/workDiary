<?php
/*
 * Created on   : Wed Jul 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : scope.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Áreas de trabajo — vistas de enfoque conmutables (Feature 082).
    'focus' => [
        'admin' => [
            'title' => 'Áreas de trabajo',
            'subtitle' => 'Elige qué áreas de trabajo ofrece tu organización en el selector, renómbralas y define una predeterminada.',
            'hint' => 'Solo una sugerencia: el área predeterminada nunca se impone — cualquiera puede cambiar en cualquier momento. Ocultar no cambia ningún permiso.',
            'list_heading' => 'Áreas ofrecidas',
            'configured_at' => 'Última modificación: :date',
            'mandatory' => 'siempre disponible',
            'is_default' => 'Predeterminada',
            'rename' => 'Nombre mostrado',
            'offered' => 'ofrecida',
            'set_default' => 'Predeterminada',
            'saved' => 'Áreas de trabajo guardadas.',
        ],
        'switcher' => 'Cambiar área de trabajo',
        'eyebrow' => 'Área de trabajo',
        'all' => 'Mostrar todo',
        'active' => 'Activa',
        'reveal' => 'Mostrar todo',
        'reveal_off' => 'Mostrar solo el enfoque',
        'dialog' => [
            'eyebrow' => 'Enfocar la vista',
            'title' => '¿En qué estás trabajando?',
            'subtitle' => 'Elige un área de trabajo — la navegación mostrará solo las áreas relevantes. No se elimina ni se bloquea nada; puedes cambiar en cualquier momento.',
            'footnote' => 'Las áreas ocultas siguen accesibles mediante la búsqueda global y «Mostrar todo».',
        ],
        'flash' => [
            'unknown' => 'Área de trabajo desconocida.',
            'switched' => 'Área de trabajo «:name» activa.',
        ],
    ],
    'title' => [
        'index' => 'Alcance funcional',
    ],
    'nav' => [
        'customize' => 'Personalizar menú',
        'functions' => 'Todas las funciones',
    ],
    'page' => [
        'subtitle' => 'Define el alcance funcional visible de la organización: preajustes para empezar rápido o módulos uno a uno.',
        'no_data_loss' => 'Desactivar solo oculta módulos y bloquea sus páginas: no se elimina ningún dato. Al reactivar, todo vuelve a estar disponible.',
    ],
    'presets' => [
        'heading' => 'Preajustes',
        'hint' => 'Un preajuste es un atajo: cambia la lista de módulos de abajo en un solo paso. Después puedes ajustar individualmente.',
        'apply' => 'Aplicar el preajuste «:preset»',
        'all_modules' => 'Todos los módulos licenciados',
        'module_count' => '{1} :count módulo adicional|[2,*] :count módulos adicionales',
    ],
    'recommendation' => [
        'heading' => 'Recomendación del perfil sectorial',
        'hint' => 'El perfil sectorial instalado «:profile» recomienda los siguientes módulos.',
        'apply' => 'Aplicar recomendación',
    ],
    'modules' => [
        'heading' => 'Definir módulos individualmente',
        'configured_at' => 'Última configuración: :date',
        'not_licensed_hint' => 'No incluido en el plan actual; ampliable mediante la gestión de licencias.',
    ],
    'flash' => [
        'saved' => 'Alcance funcional guardado (:disabled desactivados, :enabled activados). No se eliminó ningún dato.',
        'no_recommendation' => 'No hay recomendación de perfil sectorial para esta organización.',
    ],
    'customize' => [
        'subtitle' => 'Activa lo que debe aparecer en tu menú — desactiva lo que no necesites. Solo te afecta a ti, en todos los dispositivos.',
        'cosmetic_hint' => 'Ocultar no cambia los permisos: la búsqueda, los marcadores y los enlaces directos siguen funcionando. Con «Todas las funciones» lo recuperas todo.',
        'sidebar_heading' => 'Navegación lateral',
        'hide_section' => 'ocultar toda la sección',
        'hide_group' => 'ocultar subgrupo',
        'create_heading' => 'Creación rápida («Nuevo …»)',
        'create_hint' => 'Los grupos ocultos dejan de aparecer en el menú «Nuevo …» de la barra lateral.',
        'checkbox_hint' => 'Activado = visible en el menú.',
        'saved' => 'Personalización del menú guardada.',
        'unhidden' => 'La entrada vuelve a estar visible.',
    ],
    'functions' => [
        'focus_banner' => 'Área de trabajo activa «:name». Las áreas ocultas están marcadas abajo — aquí siguen accesibles.',
        'in_focus_hidden' => 'Oculto por el área de trabajo',
        'show_all' => 'Mostrar todo',
        'subtitle' => 'Resumen de todas las áreas y su estado, incluido lo oculto, desactivado o sin licencia.',
        'state' => [
            'hidden_section' => 'Sección oculta',
            'org_disabled' => 'Desactivado por la organización',
            'hidden_by_me' => 'Oculto por mí',
        ],
        'action' => [
            'unhide' => 'Mostrar',
            'enable_module' => 'Abrir alcance funcional',
        ],
        'upsell_hint' => 'Este módulo no está incluido en el plan actual.',
    ],
];
