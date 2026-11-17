<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : carry_forward.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Merker für die nächste Periode (Feature 201, MVP-1645): Komponente <x-carry-forward-notes>.
return [
    'title' => 'Recordatorios para esta vez',
    'empty' => [
        'title' => 'No hay recordatorios abiertos',
        'message' => 'No hay nada pendiente para :period de periodos anteriores.',
    ],
    'open_heading' => 'Recordatorios abiertos de periodos anteriores',
    'left_by' => 'Dejado por :name en :period, válido desde :from.',
    'resolution_label' => 'Nota al marcar como hecho',
    'resolution_placeholder' => 'Nota breve (opcional), p. ej. «certificado solicitado»',
    'resolve' => 'Marcar como hecho',
    'resolved_heading' => 'Recordatorios marcados aquí',
    'resolved_by' => 'Marcado por :name el :date',
    'left_heading' => 'Dejado para más adelante',
    'from_period' => 'desde :period',
    'withdraw' => 'Retirar recordatorio',
    'withdraw_confirm' => '¿Desea retirar realmente este recordatorio? Ya no aparecerá la próxima vez.',
    'new_label' => 'Recordatorio para la próxima vez',
    'new_hint' => 'Aparece arriba en la próxima ocurrencia de esta serie hasta que alguien lo marque como hecho. Las demás notas de este asunto no se trasladan.',
    'target_label' => 'Válido',
    'create' => 'Dejar recordatorio',
    'help' => '¿Cómo funcionan los recordatorios?',
    // Cockpit von Kunde, Lieferant, Projekt und Asset (MVP-1646).
    'cockpit' => [
        'summary' => 'Los recordatorios abiertos no se han marcado; aparecen en la siguiente aparición de la serie a partir de su periodo de destino.',
        'contracts' => 'Recordatorios en obligaciones contractuales',
        'inspections' => 'Recordatorios en obligaciones de inspección',
        'recurring_orders' => 'Recordatorios en pedidos recurrentes',
        'period_orders' => 'Recordatorios en pedidos con periodo',
    ],
    'flash' => [
        'created' => 'Recordatorio para la próxima vez guardado.',
        'resolved' => 'Recordatorio marcado como hecho.',
        'withdrawn' => 'Recordatorio retirado.',
    ],
    'error' => [
        'no_series' => 'Este asunto no pertenece a ninguna serie con periodo; aquí no es posible un recordatorio.',
        'target_not_after' => 'Un recordatorio es válido como muy pronto desde el próximo periodo.',
        'empty' => 'Introduzca un texto para el recordatorio.',
        'foreign_series' => 'Este recordatorio no pertenece a esta serie.',
        'already_resolved' => 'Este recordatorio ya está marcado como hecho.',
        'not_due' => 'Este recordatorio solo es válido desde un periodo posterior.',
    ],
];
