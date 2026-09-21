<?php
/*
 * Created on   : Fri Jun 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : onboarding.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

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
                'description' => 'Mantenga el nombre, la zona horaria y los ajustes básicos locales de la organización.',
                'link' => 'Abrir organización',
            ],
            'branch_profile' => [
                'title' => 'Elegir perfil de sector',
                'description' => 'Seleccione un perfil de sector para disponer de valores predeterminados adecuados para las clasificaciones.',
                'link' => 'Abrir perfiles de sector',
            ],
            'scope' => [
                'title' => 'Elegir el alcance funcional',
                'description' => 'Elija un preajuste de alcance funcional o ajuste los módulos activos: lo que no necesite permanece oculto sin perder datos.',
                'link' => 'Abrir alcance funcional',
            ],
            'workspaces' => [
                'title' => 'Configurar áreas de trabajo',
                'description' => 'Elija qué áreas aparecen en el selector y cuál es la predeterminada — cualquiera puede cambiar en cualquier momento.',
                'link' => 'Abrir áreas de trabajo',
            ],
        ],
        'users' => [
            'invite' => [
                'title' => 'Invitar a los primeros usuarios',
                'description' => 'Invite al menos a otra persona activa a su organización.',
                'link' => 'Abrir miembros',
            ],
        ],
        'roles' => [
            'check' => [
                'title' => 'Verificar roles',
                'description' => 'Asegúrese de que haya asignados al menos un administrador de organización y un operador.',
                'link' => 'Abrir gestión de accesos',
            ],
        ],
        'classification' => [
            'check' => [
                'title' => 'Verificar clasificaciones',
                'description' => 'Confirme o sustituya al menos un dominio de clasificación para la organización.',
                'link' => 'Abrir clasificaciones',
            ],
        ],
        'customer' => [
            'first' => [
                'title' => 'Crear el primer cliente',
                'description' => 'Añada el primer cliente manualmente o mediante importación CSV.',
                'link' => 'Abrir clientes',
            ],
        ],
        'work' => [
            'first' => [
                'title' => 'Primer proyecto o trabajo',
                'description' => 'Cree un primer proyecto o inicie la primera entrada de diario.',
                'link' => 'Abrir proyectos',
            ],
        ],
        'time' => [
            'first' => [
                'title' => 'Primera entrada de tiempo',
                'description' => 'Registre al menos una entrada de tiempo para activar el registro de tiempo.',
                'link' => 'Abrir registro de tiempo',
            ],
        ],
        'protocol' => [
            'first_signed' => [
                'title' => 'Firmar el primer protocolo',
                'description' => 'Cree un protocolo y complete la firma.',
                'link' => 'Abrir diario',
            ],
        ],
        'backup' => [
            'heartbeat' => [
                'title' => 'Heartbeat de copia de seguridad',
                'description' => 'Configure la ejecución de la copia de seguridad para que se escriban heartbeats correctos con regularidad.',
                'link' => 'Abrir registro de auditoría',
            ],
        ],
    ],
];
