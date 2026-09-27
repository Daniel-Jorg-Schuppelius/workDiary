<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : branch_profile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Branchenprofile: kundenspezifische Varianten (MVP-933).
return [
    'variant' => [
        'title' => 'Variante de perfil',
        'list' => 'Variantes específicas del cliente',
        'create' => 'Crear variante',
        'subtitle' => 'Superposición de «:base» · versión :version',
        'empty' => 'Aún no hay variantes. Una variante toma un perfil sectorial, omite elementos y añade los suyos.',
        'edit' => 'Editar',
        'save' => 'Guardar variante',
        'install' => 'Instalar',
        'force' => 'Actualizar entradas existentes',
        'export' => 'Exportar como JSON',
        'delete' => 'Eliminar',
        'confirm_delete' => '¿Eliminar la variante? Las entradas ya instaladas se conservan.',
        'installed' => 'Instalada: variante :code, versión :version.',
        'not_installed' => 'Aún no hay ninguna variante instalada de este perfil base.',
        'removals' => 'Omitir elementos',
        'additions' => 'Añadidos',
        'field' => [
            'base_code' => 'Perfil base',
            'code' => 'Código',
            'label' => 'Denominación',
            'description' => 'Descripción',
            'additions' => 'Fragmento de perfil (JSON)',
        ],
        'hint' => [
            'code' => 'Minúsculas, dígitos y guiones.',
            'removals' => 'Los elementos marcados del perfil base no se crean al instalar. Las entradas existentes no se modifican.',
            'additions' => 'Misma estructura que un perfil sectorial, p. ej. {"tags_seed": ["#propio"]}. Los elementos con el mismo código sustituyen a los del perfil base.',
        ],
        'section' => [
            'entry_type_defaults' => 'Tipos de entrada predeterminados',
            'modules_recommended' => 'Módulos recomendados',
            'classifications' => 'Clasificaciones',
            'classification_requirements' => 'Reglas obligatorias',
            'procedure_templates' => 'Plantillas de procedimiento',
            'protocol_templates' => 'Plantillas de acta',
            'asset_categories' => 'Categorías de activos',
            'tags_seed' => 'Etiquetas',
            'maintenance_plans_seed' => 'Planes de mantenimiento',
            'sla_contracts_seed' => 'Contratos SLA',
            'dataprotection_requirements_seed' => 'Requisitos de protección de datos',
            'contract_templates' => 'Plantillas de contrato',
            'training_suggestions' => 'Sugerencias de formación',
            'room_requirement_templates_seed' => 'Requisitos de sala',
            'qualifications_seed' => 'Cualificaciones',
            'custom_fields' => 'Campos personalizados',
            'cleaning_profiles_seed' => 'Perfiles de limpieza',
            'software_seed' => 'Software',
        ],
        'flash' => [
            'created' => 'Variante creada.',
            'saved' => 'Variante guardada.',
            'installed' => 'Variante «:label» instalada.',
            'deleted' => 'Variante eliminada.',
        ],
        'error' => [
            'json' => 'Los añadidos deben ser un objeto JSON.',
        ],
    ],
];
