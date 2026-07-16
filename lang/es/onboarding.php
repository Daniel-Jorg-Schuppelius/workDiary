<?php

return [
    'page' => [
        'title' => 'Onboarding',
        'heading' => 'Lista de comprobación de onboarding',
        'progress_label' => 'Progreso',
        'progress_summary' => 'Pasos obligatorios: :done de :total (:percent %)',
        'badge_required' => 'Obligatorio',
        'badge_recommended' => 'Recomendado',
        'badge_done' => 'Completado',
        'badge_open' => 'Abierto',
        'badge_skipped' => 'Omitido',
    ],
    'widget' => [
        'title' => 'Configurar el onboarding',
        'subtitle' => ':done de :total pasos obligatorios completados',
        'open_link' => 'Abrir onboarding',
        'dismiss' => 'Descartar widget',
        'dismissed_at' => 'Widget descartado: :date',
        'complete_headline' => 'Todos los pasos obligatorios completados',
        'complete_subtitle' => 'La organización está lista.',
        'open_steps' => '{0} Sin pasos abiertos|{1} :count paso abierto|[2,*] :count pasos abiertos',
    ],
    'action' => [
        'skip' => 'Omitir',
        'skip_placeholder' => 'Motivo de la omisión',
        'flash_skipped' => 'El paso de onboarding se ha omitido.',
        'flash_dismissed' => 'El widget de onboarding se ha descartado.',
        'error_step_not_skippable' => 'Este paso de onboarding no se puede omitir.',
    ],
    'step' => [
        'org' => [
            'profile' => [
                'title' => 'Completar los datos de la organización',
                'description' => 'Mantén el nombre, la zona horaria y los ajustes básicos locales de la organización.',
                'link' => 'Abrir organización',
            ],
            'branch_profile' => [
                'title' => 'Elegir perfil de sector',
                'description' => 'Selecciona un perfil de sector para disponer de valores predeterminados adecuados para las clasificaciones.',
                'link' => 'Abrir perfiles de sector',
            ],
            'scope' => [
                'title' => 'Elegir el alcance funcional',
                'description' => 'Elige un preajuste de alcance funcional o ajusta los módulos activos: lo que no necesites permanece oculto sin perder datos.',
                'link' => 'Abrir alcance funcional',
            ],
            'workspaces' => [
                'title' => 'Configurar áreas de trabajo',
                'description' => 'Elige qué áreas aparecen en el selector y cuál es la predeterminada — cualquiera puede cambiar en cualquier momento.',
                'link' => 'Abrir áreas de trabajo',
            ],
        ],
        'users' => [
            'invite' => [
                'title' => 'Invitar a los primeros usuarios',
                'description' => 'Invita al menos a otra persona activa a tu organización.',
                'link' => 'Abrir miembros',
            ],
        ],
        'roles' => [
            'check' => [
                'title' => 'Verificar roles',
                'description' => 'Asegúrate de que haya asignados al menos un administrador de organización y un operador.',
                'link' => 'Abrir gestión de accesos',
            ],
        ],
        'classification' => [
            'check' => [
                'title' => 'Verificar clasificaciones',
                'description' => 'Confirma o sustituye al menos un dominio de clasificación para la organización.',
                'link' => 'Abrir clasificaciones',
            ],
        ],
        'customer' => [
            'first' => [
                'title' => 'Crear el primer cliente',
                'description' => 'Añade el primer cliente manualmente o mediante importación CSV.',
                'link' => 'Abrir clientes',
            ],
        ],
        'work' => [
            'first' => [
                'title' => 'Primer proyecto o trabajo',
                'description' => 'Crea un primer proyecto o inicia la primera entrada de diario.',
                'link' => 'Abrir proyectos',
            ],
        ],
        'time' => [
            'first' => [
                'title' => 'Primera entrada de tiempo',
                'description' => 'Registra al menos una entrada de tiempo para activar el registro de tiempo.',
                'link' => 'Abrir registro de tiempo',
            ],
        ],
        'protocol' => [
            'first_signed' => [
                'title' => 'Firmar el primer protocolo',
                'description' => 'Crea un protocolo y completa la firma.',
                'link' => 'Abrir diario',
            ],
        ],
        'backup' => [
            'heartbeat' => [
                'title' => 'Heartbeat de copia de seguridad',
                'description' => 'Configura la ejecución de la copia de seguridad para que se escriban heartbeats correctos con regularidad.',
                'link' => 'Abrir registro de auditoría',
            ],
        ],
    ],
];
