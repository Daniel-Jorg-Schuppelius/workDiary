<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : commission.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

return [
    'title' => 'Comisiones',

    'page' => [
        'rules' => 'Reglas de comisión',
        'runs' => 'Liquidaciones de comisiones',
    ],

    'subtitle' => [
        'index' => 'Líneas de comisión por documento. La base es la factura pagada — nunca la emitida.',
        'rules' => 'Tasa por origen del lead, grupo de productos o comercial. Por documento gana una sola regla.',
        'runs' => 'Liquidar un periodo: el borrador es una vista previa, el cierre lo congela. Después solo abonos.',
    ],

    'section' => [
        'unassigned' => 'Facturas pagadas sin comisión',
        'per_user' => 'Totales por comercial',
        'run_rows' => 'Líneas de comisión de la liquidación',
    ],

    'group' => [
        'rule' => 'Regla',
        'validity' => 'Vigencia',
        'period' => 'Periodo',
    ],

    'action' => [
        'create_rule' => 'Crear regla',
        'edit_rule' => 'Editar regla',
        'edit' => 'Editar',
        'delete' => 'Eliminar',
        'save' => 'Guardar',
        'show' => 'Ver',
        'export' => 'Exportación CSV',
        'close' => 'Cerrar liquidación',
        'back' => 'Volver',
        'assign' => 'Asignar comercial',
        'create_run' => 'Crear liquidación',
        'to_rules' => 'Reglas',
        'to_runs' => 'Liquidaciones',
        'to_commissions' => 'Líneas de comisión',
    ],

    'field' => [
        'name' => 'Denominación',
        'scope' => 'Ámbito',
        'scope_value' => 'Valor del ámbito',
        'user' => 'Comercial',
        'rate_percent' => 'Tasa',
        'priority' => 'Prioridad',
        'valid_from' => 'Válida desde',
        'valid_to' => 'Válida hasta',
        'validity' => 'Vigencia',
        'is_active' => 'Activa',
        'note' => 'Nota',
        'status' => 'Estado',
        'invoice' => 'Documento',
        'customer' => 'Cliente',
        'earned_on' => 'Fecha de referencia',
        'base_amount' => 'Base de cálculo',
        'commission_amount' => 'Comisión',
        'run' => 'Liquidación',
        'period' => 'Periodo',
        'period_start' => 'Periodo desde',
        'period_end' => 'Periodo hasta',
        'currency' => 'Moneda',
        'entry_count' => 'Líneas',
        'total_base' => 'Total base',
        'total_commission' => 'Total comisión',
        'closed_by' => 'Cerrada por',
        'paid_on' => 'Pagada el',
    ],

    'scope' => [
        'all' => 'Todos los documentos',
        'lead_source' => 'Origen del lead',
        'product_group' => 'Grupo de productos',
        'user' => 'Comercial',
    ],

    'status' => [
        'pending' => 'Abierta',
        'settled' => 'Liquidada',
        'reversed' => 'Abonada',
    ],

    'run_status' => [
        'draft' => 'Borrador',
        'closed' => 'Cerrada',
    ],

    'assignment' => [
        'lead' => 'Del pipeline de leads',
        'manual' => 'Asignada manualmente',
    ],

    'badge' => [
        'reversal' => 'Abono',
    ],

    'empty' => [
        'rules' => 'Todavía no hay ninguna regla de comisión.',
        'commissions' => 'Todavía no hay ninguna línea de comisión.',
        'runs' => 'Todavía no se ha creado ninguna liquidación.',
        'run_rows' => 'No hay líneas de comisión en este periodo.',
    ],

    'hint' => [
        'scope_value' => 'Solo para el ámbito origen del lead o grupo de productos; debe coincidir con el ámbito elegido.',
        'user' => 'Solo para el ámbito comercial.',
        'priority' => 'Gana el número más alto; en caso de empate decide el ámbito más estrecho.',
        'period' => 'Etiqueta legible, p. ej. 2026-08. Vacío = derivada de la fecha de inicio.',
        'currency' => 'Una liquidación trata exactamente una moneda — las comisiones nunca se convierten.',
        'assign' => 'Dejar vacío para volver al origen del pipeline de leads.',
        'current_assignment' => 'Responsable actual: :user (:source).',
        'no_assignment' => 'Ahora mismo no hay responsable — sin asignación no se genera ninguna comisión.',
        'unassigned' => 'Estas facturas están pagadas pero sin asignar: ni manualmente ni mediante un lead convertido.',
        'draft_preview' => 'Borrador: las líneas se recalculan en cada visita. Solo el cierre las congela.',
        'no_payout' => 'WorkDiary calcula y exporta la comisión — el pago se realiza en la nómina.',
    ],

    'confirm' => [
        'delete_rule' => '¿Eliminar la regla de comisión? Las comisiones ya calculadas no se modifican.',
        'delete_run' => '¿Eliminar el borrador de la liquidación?',
        'close_run' => '¿Cerrar la liquidación? Después queda congelada; las correcciones solo pasan por un abono.',
    ],

    'flash' => [
        'rule_created' => 'Regla de comisión creada.',
        'rule_updated' => 'Regla de comisión guardada.',
        'rule_deleted' => 'Regla de comisión eliminada.',
        'assigned' => 'Asignación guardada.',
        'run_created' => 'Liquidación creada.',
        'run_closed' => 'Liquidación cerrada y congelada.',
        'run_deleted' => 'Liquidación eliminada.',
    ],

    'error' => [
        'period_reversed' => 'El fin del periodo es anterior a su inicio.',
        'period_overlap' => 'Ya existe una liquidación para este periodo.',
        'already_closed' => 'Esta liquidación ya está cerrada.',
    ],

    'note' => [
        'credit_note' => 'Abono por la nota de crédito :number',
        'cancelled' => 'Abono por anulación',
        'reassigned' => 'Abono por reasignación del comercial',
    ],

    'export' => [
        'period' => 'Periodo',
        'user' => 'Comercial',
        'invoice' => 'Documento',
        'customer' => 'Cliente',
        'earned_on' => 'Fecha de referencia',
        'currency' => 'Moneda',
        'base' => 'Base de cálculo',
        'rate' => 'Tasa en porcentaje',
        'commission' => 'Comisión',
        'kind' => 'Tipo',
        'note' => 'Nota',
        'reversal' => 'Abono',
        'regular' => 'Comisión',
    ],
];
