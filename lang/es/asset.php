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
    // Anlagen-Stückliste (Feature 118, MVP-607).
    'components' => [
        'title' => 'Lista de componentes',
        'stock_serial_none' => 'ninguno (pieza ajena)',
        'stock_serial_hint' => 'Solo las piezas del propio almacén tienen uno.',
        'serial_no_hint' => 'Texto libre; se sobrescribe si se vincula un número de serie de almacén.',
        'empty' => 'Aún no hay ninguna pieza registrada.',
        'saved' => 'Pieza registrada.',
        'replaced' => 'Pieza sustituida — la anterior permanece en el historial.',
        'removed' => 'Pieza desmontada.',
        'not_installed' => 'Esta pieza ya no está montada.',
        'foreign_organization' => 'El consumo de material y el equipo pertenecen a organizaciones distintas.',
        'usage_of_other_asset' => 'El consumo de material pertenece a otro equipo.',
        'already_adopted' => 'Este consumo de material ya figura en la lista de piezas.',
        'adopted' => 'Material añadido a la lista de piezas.',
        'replace_hint' => '«:name» se desmonta y permanece en el historial con su fecha de retirada.',
        'label_hint' => 'Para piezas de terceros sin ficha de artículo.',
        'interval_hint' => 'Intervalo de sustitución en meses — de ahí sale el vencimiento.',
        'action' => [
            'add' => 'Añadir pieza',
            'replace' => 'Sustituir',
            'remove' => 'Desmontar',
            'adopt' => 'Adoptar',
        ],
        'adopt' => ['heading' => 'Material de las intervenciones', 'hint' => 'Material asignado a este equipo en un parte de horas. «Adoptar» lo añade a la lista de piezas con el día de la intervención como fecha de montaje.', 'column_date' => 'Intervención'],
        'due' => ['heading' => 'Piezas de desgaste pendientes', 'hint' => 'Propuesta para la próxima intervención — decide el técnico.'],
        'history' => ['heading' => 'Historial (piezas desmontadas y sustituidas)'],
        'column' => [
            'name' => 'Pieza',
            'article' => 'Artículo',
            'label' => 'Denominación (texto libre)',
            'position' => 'Ubicación',
            'quantity' => 'Cantidad',
            'unit' => 'Unidad',
            'serial_no' => 'Número de serie',
            'stock_serial' => 'Número de serie de almacén',
            'installed_on' => 'Montada el',
            'removed_on' => 'Desmontada el',
            'due_on' => 'Sustitución prevista',
            'interval' => 'Intervalo (meses)',
            'status' => 'Estado',
            'note' => 'Nota',
        ],
        'status' => [
            'installed' => 'Montada',
            'removed' => 'Desmontada',
            'replaced' => 'Sustituida',
        ],
    ],
    'label' => [
        'print' => 'Etiqueta con QR',
    ],
    'nfc' => [
        'read' => 'Leer etiqueta NFC',
        'write' => 'Escribir etiqueta NFC',
    ],
    // Zustand, Außerbetriebnahme, Eigentümerwechsel, Rücknahme (MVP-1574).
    'health' => [
        'label' => 'Estado',
        'hint' => 'Derivado: crítico con un bloqueo activo o un defecto bloqueante, limitado con un defecto abierto o mantenimiento/inspección vencidos.',
    ],
    'decommission' => [
        'title' => 'Dar de baja el activo',
        'action' => 'Dar de baja',
        'intro' => 'El activo se da de baja definitivamente. Después ya no se puede entregar, alquilar, reservar ni planificar; su expediente se conserva.',
        'date' => 'Fecha de baja',
        'reason' => 'Motivo',
        'since' => 'De baja desde el :date',
        'by_disposal' => 'Eliminado mediante una orden de eliminación.',
        'done' => 'Activo dado de baja.',
        'plans' => 'Planes de mantenimiento activos: :count — se pausarán.',
        'duties' => 'Obligaciones de inspección activas: :count — quedan suspendidas, las citas de inspección abiertas se cancelan.',
        'error' => [
            'checked_out' => 'El activo sigue entregado. Recójalo primero.',
            'not_allowed' => 'El activo no se puede dar de baja desde el estado «:status».',
        ],
    ],
    // Wiederinbetriebnahme (MVP-1628)
    'recommission' => [
        'title' => 'Volver a poner el activo en servicio',
        'action' => 'Volver a poner en servicio',
        'intro' => 'El activo vuelve a estar activo: se puede entregar, prestar, reservar y planificar. Los planes de mantenimiento pausados y las obligaciones de inspección inactivas siguen desactivados; reactívelos uno a uno.',
        'reason' => 'Motivo de la nueva puesta en servicio',
        'done' => 'Activo puesto de nuevo en servicio.',
        'error' => [
            'not_decommissioned' => 'Solo un activo fuera de servicio puede volver a ponerse en servicio.',
            'disposed' => 'El activo se eliminó mediante una orden de eliminación y sigue fuera de servicio.',
        ],
    ],
    'ownership' => [
        'title' => 'Cambiar propietario',
        'action' => 'Cambiar propietario',
        'current' => 'Actualmente: :owner',
        'owned_by' => 'Propiedad',
        'customer_hint' => 'Solo con propiedad «Cliente»; en otro caso se elimina el vínculo con el cliente.',
        'note' => 'Nota sobre el cambio de propietario',
        'note_hint' => 'Solo se guarda si cambia el cliente; el cambio figura entonces en el expediente.',
        'done' => 'Cambio de propietario registrado.',
        'error' => [
            'unchanged' => 'La propiedad y el cliente no han cambiado.',
            'forbidden' => 'Otro cliente supone un cambio de propietario; no tiene permiso para ello.',
        ],
    ],
    'return' => [
        'title' => 'Recoger el activo',
        'condition_in' => 'Estado en la devolución',
        'condition_placeholder' => 'p. ej. completo, carcasa rayada',
    ],
    'timeline' => [
        'ownership_changed' => 'Propietario cambiado',
        'health_changed' => 'Estado modificado',
        'blocked' => 'Bloqueado',
        'unblocked' => 'Bloqueo levantado',
    ],
    'block' => [
        'from_status' => 'Por el estado «:status».',
        'status_left' => 'Estado modificado.',
    ],
    'guard' => [
        'out_of_service' => ':asset ya no está en servicio (:status) y no se puede utilizar.',
    ],
    // Anschaffung an der Asset-Akte (MVP-1606).
    'acquisition' => [
        'title' => 'Adquisición',
        'supplier' => 'Adquirido a',
        'date' => 'Fecha de adquisición',
        'cost' => 'Coste de adquisición',
    ],
];
