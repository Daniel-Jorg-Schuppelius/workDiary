<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : numbering.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Vergabe von Kunden- und Lieferantennummern durch ein Buchhaltungsprogramm (Phase 141, MVP-1130–1134),
// Seite „Nummernkreise“ (Phase 144, MVP-1147–1149).
return [
    'source' => [
        'local' => 'workDiary',
        'inactive' => ':program (inactivo – asigna workDiary)',
        'column' => 'Asignado por',
        'page_hint' => 'Si un programa de contabilidad asigna los números de cliente o de proveedor, un registro nuevo recibe su número al transferirse. Los números existentes no cambian. Los números de factura, abono y anulación los asigna el programa de la vía de facturación, o Lexoffice si allí está activado el interruptor. Si un programa asigna los números, el formato queda bloqueado.',
        'billing' => ':program (vía de facturación)',
        'deviation' => ':program para un cliente|:program para :count clientes',
    ],
    'field' => [
        'locked_hint' => ':program asigna el número al transferir.',
        'row' => ':field (:scope)',
    ],
    'separators' => [
        'prefix' => 'Separador tras el prefijo, todas las series',
        'year' => 'Separador tras el año, todas las series',
        'apply' => 'Aplicar a todas las series',
        'hint' => 'Aplica ambos separadores a todas las series que asigna workDiary y guarda la página. Un campo vacío significa sin separador. Los números ya asignados no cambian.',
    ],
    'preview' => [
        'program' => 'Lo fija :program',
        'locked_hint' => 'El formato y el número los fija :program.',
        'next_remote' => 'Siguiente en :program: :number',
        'last_adopted' => 'Último de :program: :number',
        'pending' => 'Un contacto espera su número.|:count contactos esperan su número.',
    ],
    'issue' => [
        'no_issuer' => ':program no puede transferir :scope. Elija en la página «Series numéricas» quién asigna el número.',
        'quote_pending' => ':program asigna el número de la oferta al enviarla; hasta entonces lleva un número provisional.',
        'draft_number' => 'Este documento lleva un número provisional y :program no puede asumirlo. Defina en la página «Series numéricas» quién asigna el número.',
        'transfer_failed' => 'La transferencia a :program ha fallado; el documento sigue como borrador. :error',
        'issue_first' => '«:document» aún lleva un número provisional. Emita primero el documento: el programa de contabilidad asigna entonces el número.',
        'invoice_pending' => ':program asigna el número al emitir; WorkDiary transfiere el documento en ese momento. Hasta entonces lleva un número provisional.',
    ],
    'flash' => [
        'saved' => 'Una serie numérica guardada.|:count series numéricas guardadas.',
        'unchanged' => 'Sin cambios.',
        'transfer_pending' => 'Documento «:document» creado. Al emitirlo, WorkDiary lo transfiere a :program, que también le asigna su número.',
        'issued' => 'Número :number tomado de :program.',
        'pending' => 'El número de :program llegará con la próxima transferencia: :reason',
        'conflict' => 'El número :number de :program ya está asignado a :holder. Encontrará el caso en la bandeja de asignación.',
    ],
    'conflict' => [
        'title' => 'El número :number de :program ya está asignado a :holder',
    ],
    'error' => [
        'taken_remote' => 'El número :number ya está asignado a «:name» en :program.',
        'taken_local' => 'El número :number ya está asignado a :holder. Cambie primero el número allí.',
        'no_number' => ':program no ha devuelto ningún número.',
    ],
];
