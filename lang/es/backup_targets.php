<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : backup_targets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Destinos de copia de seguridad en la nube',
    'description' => 'Copias externas cifradas de toda la instalación (estrategia 3-2-1). El texto en claro nunca sale de la instalación — solo se suben partes cifradas.',

    'master_key_missing' => 'BACKUP_MASTER_KEY no está definida — sin la clave de copia de la instalación no se pueden crear ni restaurar copias.',
    'recovery_key_missing' => 'No hay clave de recuperación configurada: si se pierde BACKUP_MASTER_KEY, todas las copias en la nube se pierden irremediablemente. Defina BACKUP_RECOVERY_PUBLIC_KEY y guarde la clave privada sin conexión.',

    'connect' => 'Conectar',
    'reconnect' => 'Volver a iniciar sesión',
    'disconnect' => 'Desconectar',
    'disconnect_confirm' => '¿Desconectar realmente? Los datos remotos permanecen intactos; las copias programadas se detienen.',
    'cleanup' => 'Limpieza',
    'no_connections' => 'Aún no hay ningún destino de copia conectado.',
    'account' => 'Cuenta',
    'quota' => 'Almacenamiento',
    'quota_value' => ':used de :total usados',
    'quota_unknown' => 'Uso de almacenamiento desconocido',
    'pilot_note' => 'Piloto pendiente: este adaptador aún no se ha probado contra el proveedor real.',

    'generations' => [
        'title' => 'Generaciones de copia',
        'empty' => 'Todavía no hay ninguna generación de copia.',
        'snapshot' => 'Instantánea',
        'target' => 'Destino',
        'class' => 'Clase',
        'age' => 'Creada',
        'size' => 'Tamaño',
        'status' => 'Estado',
        'verified' => 'Verificada',
        'restore_tested' => 'Prueba de restauración',
        'restore_pending' => 'guardada, restauración no confirmada',
        'hold' => 'Retención legal',
        'actions' => 'Acciones',
        'hold_set_action' => 'Activar retención',
        'hold_release_action' => 'Liberar retención',
        'delete_action' => 'Eliminar',
        'delete_confirm' => '¿Eliminar realmente esta generación? Se retirarán los datos remotos y el registro.',
    ],

    'cleanup_page' => [
        'title' => 'Limpieza — inventario remoto',
        'description' => 'Vista previa de los objetos del área de copia de esta conexión. La eliminación solo ocurre tras confirmar por generación.',
        'empty' => 'No se encontraron objetos remotos en el área de copia.',
        'known' => 'Generación conocida',
        'orphan' => 'Huérfana (sin registro en la base de datos)',
        'error' => 'No se pudo cargar el inventario remoto: :message',
        'back' => 'Volver a la vista general',
    ],

    'flash' => [
        'not_configured' => 'El proveedor no está configurado (faltan el client ID/secret).',
        'state_invalid' => 'El proceso de inicio de sesión caducó o no es válido — inténtelo de nuevo.',
        'oauth_denied' => 'La autorización fue cancelada o denegada.',
        'oauth_failed' => 'Intercambio de tokens fallido (:class).',
        'account_failed' => 'Confirmación de la cuenta fallida (:class).',
        'scope_missing' => 'Falta el permiso requerido (:scope) — el destino está bloqueado.',
        'connected' => 'Destino de copia conectado y activo.',
        'disconnected' => 'Conexión eliminada. Los datos remotos permanecen intactos.',
        'hold_set' => 'Retención legal activada — la generación está protegida contra la eliminación.',
        'hold_released' => 'Retención legal liberada.',
        'hold_blocks_delete' => 'Esta generación tiene una retención legal y no puede eliminarse.',
        'cleanup_failed' => 'Limpieza remota fallida (:class).',
        'generation_deleted' => 'Generación retirada (remoto y registro).',
    ],
];
