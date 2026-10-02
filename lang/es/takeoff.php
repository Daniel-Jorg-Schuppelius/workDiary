<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : takeoff.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Aufmaßblatt (MVP-1058).
return [
    'title' => 'Medición',
    'back' => 'Volver',
    'default_title' => 'Medición :carrier',
    'lines' => 'Líneas de medición',
    'totals' => 'Cantidades por posición',
    'photos' => 'Fotos y croquis',
    'values' => 'Valores',
    'empty' => 'Todavía no hay líneas — añada una fórmula con «Añadir línea».',
    'no_target' => '— sin asignar —',
    'pdf_note' => 'Calculado con las fórmulas de la REB-VB 23.003. Valores en metros, ángulos en gonios (círculo completo = 400).',
    'formula' => [
        'Sum' => 'Unidades / suma',
        'Triangle' => 'Triángulo',
        'Rectangle' => 'Rectángulo / prisma',
        'Trapezoid' => 'Trapecio',
        'Circle' => 'Círculo / sector',
        'Mean' => 'Media',
        'Free' => 'Fórmula libre',
    ],
    'value' => [
        'amount' => 'Valor',
        'base' => 'Base',
        'height' => 'Altura',
        'depth' => 'Profundidad / altura (cuerpo, opcional)',
        'length' => 'Longitud',
        'width' => 'Anchura',
        'side_a' => 'Lado a',
        'side_c' => 'Lado c',
        'radius' => 'Radio',
        'angle' => 'Ángulo en gonios (400 = círculo completo)',
        'expression' => 'Expresión',
    ],
    'hint' => [
        'Sum' => 'Los valores se suman; un valor negativo resta.',
        'Triangle' => 'Base × altura ÷ 2; con profundidad como cuerpo.',
        'Rectangle' => 'Longitud × anchura; con profundidad/altura como cuerpo.',
        'Trapezoid' => '(a + c) ÷ 2 × altura; con profundidad como cuerpo.',
        'Circle' => 'Radio² × π × ángulo ÷ 400; 400 gonios es el círculo completo.',
        'Mean' => 'Media aritmética de los valores.',
        'Free' => 'Expresión con + − × ÷ y paréntesis, coma o punto decimal.',
        'factor' => 'Número de piezas iguales; negativo resta (p. ej. −1 para una puerta).',
        'label' => 'Estancia, elemento o eje.',
        'unit' => 'Vacío = unidad de la posición o del artículo.',
    ],
    'col' => [
        'label' => 'Estancia / elemento',
        'formula' => 'Fórmula',
        'values' => 'Valores',
        'factor' => 'Factor',
        'quantity' => 'Cantidad',
        'target' => 'Posición',
    ],
    'field' => [
        'title' => 'Denominación',
        'measured_on' => 'Medido el',
        'note' => 'Observación',
        'boq_item' => 'Posición del listado',
        'article' => 'Artículo / prestación',
        'description' => 'Descripción (sin artículo)',
        'unit' => 'Unidad',
    ],
    'action' => [
        'create' => 'Nueva medición',
        'edit' => 'Editar',
        'pdf' => 'PDF',
        'delete' => 'Eliminar',
        'add_line' => 'Añadir línea',
    ],
    'transition' => [
        'completed' => 'Cerrar',
        'draft' => 'Reabrir',
    ],
    'confirm' => [
        'completed' => '¿Cerrar la medición? Las líneas quedan bloqueadas y las cantidades se pueden traspasar.',
        'draft' => '¿Reabrir la medición? Las cantidades ya traspasadas no cambian.',
        'delete' => '¿Eliminar la medición con todas sus líneas?',
        'delete_line' => '¿Desea eliminar esta línea?',
    ],
    'flash' => [
        'created' => 'Medición creada.',
        'saved' => 'Medición guardada.',
        'deleted' => 'Medición eliminada.',
        'status' => 'Estado cambiado.',
        'line_saved' => 'Línea guardada.',
        'line_deleted' => 'Línea eliminada.',
    ],
    'error' => [
        'locked' => 'La medición está cerrada y ya no se puede modificar.',
        'not_computable' => 'Con estos valores no se puede calcular la fórmula — revise los valores obligatorios o la expresión.',
        'not_found' => 'Medición no encontrada.',
    ],
    'carrier' => [
        'section' => 'Mediciones',
        'lines' => ':count línea|:count líneas',
        'none' => 'Todavía no hay mediciones.',
    ],
    'transfer' => [
        'title' => 'Traspasar cantidades',
        'action' => 'Traspasar',
        'confirm' => [
            'quote' => '¿Traspasar las cantidades a un nuevo presupuesto?',
            'invoice' => '¿Traspasar las cantidades a un borrador de factura? El PDF de la medición se adjunta como documento.',
            'progress' => '¿Comunicar las cantidades como avance de las partidas del presupuesto de obra?',
        ],
        'targets' => 'Traspasado a',
        'kind' => [
            'quote' => 'Como presupuesto',
            'invoice' => 'Como borrador de factura',
            'progress' => 'Como avance del listado',
        ],
        'hint' => 'Cada tipo una vez por medición; las posiciones sin precio reciben 0 € y se completan en el documento.',
        'done' => 'Traspasado',
        'based_on' => 'Cantidades según la medición «:title».',
        'document_title' => 'Medición :title',
        'progress_note' => 'De la medición «:title»',
        'flash' => [
            'quote' => 'Presupuesto creado a partir de la medición.',
            'invoice' => 'Borrador de factura creado a partir de la medición; el PDF de la medición está adjunto como documento.',
            'progress' => ':count posiciones del listado notificadas.',
        ],
        'error' => [
            'not_completed' => 'Cierre primero la medición.',
            'already' => 'Ya traspasado: :kind.',
            'no_customer' => 'No hay ningún cliente vinculado al pedido o proyecto.',
            'empty' => 'La medición no tiene cantidades.',
            'no_boq' => 'Ninguna línea está asignada a una posición del listado.',
        ],
    ],
    'chain' => [
        'label' => 'Mediciones sin documento',
        'measured_on' => 'Medido el :date',
    ],
    'presets' => [
        'label' => 'Plantillas',
    ],
    'quick' => [
        'title' => 'Registro rápido',
        'hint' => 'Funciona también sin conexión — la línea se envía con la próxima conexión.',
        'photo' => 'Foto',
        'add' => 'Registrar línea',
    ],
];
