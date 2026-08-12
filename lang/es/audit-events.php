<?php

return [
    'created' => 'Creado',
    'updated' => 'Actualizado',
    'deleted' => 'Eliminado',
    'archived' => 'Archivado',
    'restored' => 'Restaurado',
    'auth' => [
        'login' => 'Inicio de sesión',
        'logout' => 'Cierre de sesión',
        'failed' => 'Inicio de sesión fallido',
        'password_reset' => 'Restablecimiento de contraseña',
    ],
    'onboarding' => [
        'completed' => 'Onboarding completado',
        'stepCompleted' => 'Paso de onboarding completado',
        'stepSkipped' => 'Paso de onboarding omitido',
        'widgetDismissed' => 'Widget de onboarding descartado',
    ],
    'backup' => [
        'completed' => 'Copia de seguridad completada',
    ],
    'import' => [
        'confirmed' => 'Importación confirmada',
        'started' => 'Importación iniciada',
        'finished' => 'Importación finalizada',
        'partial' => 'Importación finalizada parcialmente',
        'preflightFailed' => 'Comprobación previa de la importación fallida',
    ],
    'diagnostics' => [
        'viewed' => 'Diagnóstico consultado',
        'testTriggered' => 'Prueba de diagnóstico activada',
    ],
    'role' => [
        'created' => 'Rol creado',
        'updated' => 'Rol actualizado',
        'deleted' => 'Rol eliminado',
    ],
    'user_group' => [
        'member_added' => 'Grupo de usuarios: miembro añadido',
        'member_removed' => 'Grupo de usuarios: miembro eliminado',
    ],
    // Asignación de roles/permisos (Bauturbo A17, MVP-335)
    'user' => [
        'role' => [
            'assigned' => 'Rol asignado',
            'revoked' => 'Rol revocado',
        ],
        'permission' => [
            'granted' => 'Permiso concedido',
            'revoked' => 'Permiso revocado',
        ],
    ],
    'support' => [
        'test' => 'Prueba de soporte',
        'reportGenerated' => 'Informe de soporte generado',
        'reportDownloaded' => 'Informe de soporte descargado',
    ],
    'report' => [
        'exported' => 'Informe exportado',
        'presenceEmergencyViewed' => 'Lista de presencia de emergencia consultada',
    ],
    'rules' => [
        'recalculated' => 'Resultados de reglas de tiempo recalculados',
    ],
    'timeDimension' => [
        'type_created' => 'Tipo de dimensión de tiempo creado',
        'type_toggled' => 'Tipo de dimensión de tiempo conmutado',
        'value_created' => 'Valor de dimensión de tiempo creado',
        'value_deleted' => 'Valor de dimensión de tiempo eliminado',
    ],
    'limit' => [
        'exceeded' => 'Límite superado',
    ],
    'license' => [
        'installed' => 'Licencia instalada',
    ],
    'asset' => [
        'created' => 'Activo creado',
    ],
    'protocol' => [
        'signatureRequested' => 'Firma solicitada',
        'signatureLinkOpened' => 'Enlace de firma abierto',
    ],
    'session' => [
        'revoked' => 'Sesión revocada',
    ],
    'token' => [
        'revoked' => 'Token revocado',
    ],
    // ArbZG-Compliance-Verstöße (Feature 006, Welle D)
    'compliance' => [
        'finding' => [
            'detected' => 'Infracción detectada',
            'acknowledged' => 'Infracción confirmada',
            'accepted' => 'Infracción aceptada',
            'resolved' => 'Infracción resuelta',
            'reopened' => 'Infracción reaparecida',
        ],
    ],
    'privacy' => [
        'overviewExported' => 'Resumen de privacidad exportado',
        'report' => [
            'exported' => 'Informe de protección de datos exportado',
        ],
    ],
    'integration' => [
        'changed' => 'Integración activada/desactivada',
    ],
    'tenant' => [
        'export' => [
            'requested' => 'Exportación del inquilino solicitada',
        ],
    ],
    'branch_profile' => [
        'installed' => 'Perfil de sucursal instalado',
    ],
    'demo' => [
        'reset' => 'Inquilino de demostración restablecido',
        'seeded' => 'Datos de demostración generados',
    ],
    'dayClose' => [
        'opened' => 'Cierre diario abierto',
        'entrySaved' => 'Cierre diario guardado',
        'closed' => 'Día cerrado',
        'correctionRequested' => 'Corrección del día solicitada',
        'correctionApproved' => 'Corrección del día aprobada',
        'correctionRejected' => 'Corrección del día rechazada',
        'reopened' => 'Día reabierto',
    ],
    // Registros de tiempo (MVP-508)
    'timeEntry' => [
        'reassigned' => 'Registro de tiempo reasignado a otro usuario',
    ],
    // Accesos al portal de clientes (MVP-510)
    'portal' => [
        'query' => [
            'withdrawn' => 'Consulta del portal retirada',
        ],
        'visibility' => [
            'updated' => 'Visibilidad del portal modificada',
        ],
        'access' => [
            'invited' => 'Acceso al portal invitado',
            'invite_resent' => 'Invitación al portal reenviada',
            'invite_accepted' => 'Invitación al portal aceptada',
            'deactivated' => 'Acceso al portal desactivado',
            'reactivated' => 'Acceso al portal reactivado',
        ],
    ],
];
