<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : datev.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// DATEV-Online-Plugin (MVP-122), Aufruf mit `datev-online::datev.…`.
return [
    'plugin' => [
        'description' => 'DATEV Online: inicio de sesión con DATEV, lotes contables por importación EXTF en lugar de descarga e imágenes de justificantes cada noche a DATEV Unternehmen online.',
    ],
    'settings' => [
        'client_id' => 'ID de cliente (portal de desarrolladores de DATEV)',
        'client_id_help' => 'Del registro de la aplicación en DATEV; registre allí esta dirección de redirección: :url',
        'client_secret' => 'Secreto de cliente',
        'sandbox' => 'Usar sandbox',
        'sandbox_help' => 'Entorno de pruebas de DATEV. Desactívelo para producción solo tras la autorización de DATEV.',
    ],
    'health' => [
        'not_configured' => 'No hay ningún registro de aplicación de DATEV guardado.',
        'not_connected' => 'Sin sesión iniciada en DATEV.',
        'no_client' => 'No se ha seleccionado ningún mandante.',
        'last_error' => 'Último error: :error',
        'ok' => 'Conectado con :client.',
    ],
    'connection_status' => [
        'active' => 'conectado',
        'disconnected' => 'no conectado',
    ],
    'transfer_kind' => [
        'extf' => 'Lote contable',
        'outgoing_document' => 'Factura emitida',
        'incoming_document' => 'Factura recibida',
    ],
    'transfer_status' => [
        'pending' => 'en proceso',
        'transferred' => 'transferido',
        'succeeded' => 'importado',
        'failed' => 'fallido',
    ],
    'error' => [
        'unknown_client' => 'Este mandante no está habilitado para la sesión iniciada.',
        'not_ready' => 'Inicie primero sesión con DATEV y seleccione un mandante.',
        'batch_not_exported' => 'Solo se pueden transferir lotes contables cerrados.',
        'client_mismatch' => 'El lote pertenece a un asesor o mandante distinto del conectado.',
        'file_missing' => 'Falta el archivo del lote en el almacenamiento.',
        'transfer_failed' => 'DATEV no ha aceptado el lote; los detalles figuran en la lista.',
    ],
    'flash' => [
        'not_configured' => 'DATEV Online no está configurado: faltan el ID y el secreto de cliente.',
        'state_invalid' => 'Estado de inicio de sesión no válido o caducado; vuelva a iniciar sesión.',
        'oauth_denied' => 'Se canceló el inicio de sesión con DATEV.',
        'oauth_failed' => 'Falló el intercambio de tokens con DATEV (:class).',
        'connected' => 'Sesión iniciada con DATEV. Seleccione el mandante.',
        'disconnected' => 'Conexión con DATEV cerrada.',
        'client_selected' => 'Mandante :client seleccionado.',
        'documents_saved' => 'Configuración de imágenes de justificantes guardada.',
        'uploaded' => ':transferred justificantes transferidos, :failed fallidos.',
        'batch_transferred' => 'Lote :no entregado a DATEV; DATEV está procesando la importación.',
        'jobs_refreshed' => ':count importaciones finalizadas.',
    ],
    'page' => [
        'subtitle' => 'Transferir lotes contables e imágenes de justificantes directamente a DATEV Unternehmen online.',
        'sandbox' => 'Sandbox',
        'connect' => 'Iniciar sesión con DATEV',
        'disconnect' => 'Desconectar',
        'disconnect_confirm' => '¿Desconectar realmente de DATEV?',
        'not_configured' => 'En la configuración del plugin faltan el ID y el secreto de cliente del registro de la aplicación de DATEV.',
        'client' => [
            'heading' => 'Mandante',
            'choose' => 'Mandante (asesor-mandante)',
            'save' => 'Aplicar',
            'error' => 'No se puede obtener la lista de mandantes (:class).',
            'none' => 'No hay mandantes habilitados para esta sesión.',
        ],
        'documents' => [
            'heading' => 'Imágenes de justificantes',
            'hint' => 'Las facturas emitidas se envían a DATEV Unternehmen online como «Rechnungsausgang» y las recibidas como «Rechnungseingang», cada una una vez y a partir de la fecha elegida.',
            'enabled' => 'Transferir imágenes de justificantes cada noche',
            'since' => 'A partir de la fecha del justificante',
            'save' => 'Guardar',
            'upload' => 'Transferir ahora',
        ],
        'batches' => [
            'heading' => 'Lotes contables',
            'hint' => 'Los lotes cerrados se envían a DATEV como importación EXTF; el asesor y el mandante del lote deben coincidir con el mandante conectado.',
            'empty' => 'No hay lotes contables cerrados.',
            'transfer' => 'Transferir a DATEV',
            'refresh' => 'Consultar estado de importación',
            'col' => [
                'batch' => 'Lote',
                'period' => 'Período',
                'client' => 'Asesor-mandante',
                'status' => 'DATEV',
            ],
        ],
        'transfers' => [
            'heading' => 'Justificantes transferidos recientemente',
            'empty' => 'Aún no se ha transferido ningún justificante.',
            'col' => [
                'kind' => 'Tipo',
                'date' => 'Momento',
                'status' => 'Estado',
            ],
        ],
    ],
];
