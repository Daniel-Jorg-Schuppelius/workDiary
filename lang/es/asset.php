<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : asset.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'lifecycle' => [
        'in_operation' => 'En servicio',
        'retired' => 'Sustituido',
        'decommissioned' => 'Dado de baja',
    ],
    'dossier' => [
        'title' => 'Expediente del objeto',
        'back' => 'Volver al activo',
        'generated_at' => 'Generado el',
        'lifecycle' => 'Ciclo de vida',
        'master_data' => 'Datos maestros',
        'health' => 'Estado',
        'commissioned' => 'Puesta en servicio',
        'decommissioned' => 'Baja',
        'warranty' => 'Garantía hasta',
        'warranty_expired' => 'caducada',
        'in_service_days' => 'En servicio (días)',
        'room_requirements' => 'Requisitos de la sala',
        'maintenance' => 'Mantenimientos',
        'next_due' => 'Próximo vencimiento',
        'last_run' => 'Última realización',
        'due' => 'Vencido',
        'scheduled' => 'Planificado',
        'assignments' => 'Entregas / devoluciones',
        'checked_out' => 'Entregado',
        'assignee' => 'Destinatario',
        'returned' => 'Devuelto',
        'open' => 'Abierto',
        'defects' => 'Defectos / bloqueos',
        'blocks' => 'Bloquea',
        'orders' => 'Órdenes',
        'timeline' => 'Historial del ciclo de vida',
        'event' => [
            'asset.audit' => 'Evento del activo',
            'order.linked' => 'Orden vinculada',
            'protocol.linked' => 'Protocolo vinculado',
            'material.linked' => 'Uso de material vinculado',
            'attachment.linked' => 'Adjunto añadido',
            'assignment.checkedOut' => 'Entregado',
            'assignment.returned' => 'Devuelto',
            'defect.reported' => 'Defecto notificado',
            'defect.resolved' => 'Defecto resuelto',
            'maintenance.completed' => 'Mantenimiento realizado',
            'unknown' => 'Evento',
        ],
    ],
];
