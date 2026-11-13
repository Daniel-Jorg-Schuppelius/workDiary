<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : incoming_pdf.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// PDF agrupado y GiroCode en la entrada de facturas (Feature 208, MVP-1662/1663).
return [
    'split' => [
        'title' => 'Dividir PDF agrupado',
        'card_title' => 'PDF agrupado',
        'gate' => 'PDF agrupado: primero dividir o confirmar como una sola factura.',
        'part_description' => 'Parte de «:title», páginas :from–:to',
        'document_title' => 'PDF agrupado :file (:count facturas)',
        'document_description' => 'Original sin cambios, dividido en :count facturas recibidas.',
        'state' => [
            'pending' => 'Se están comprobando las páginas en busca de varias facturas. Hasta entonces esperan el traspaso y el pago.',
            'proposed' => 'El PDF contiene probablemente :count facturas. El traspaso y el pago esperan hasta que usted decida.',
            'single' => 'Las :pages páginas pertenecen probablemente a una sola factura. Si aun así hay varias, divídalas a mano.',
            'dismissed' => 'Confirmado como una sola factura.',
            'splitting' => 'La división se está realizando en segundo plano.',
            'too_large' => 'Más de :max páginas — no se comprueba si hay varias facturas.',
            'failed' => 'La comprobación de varias facturas ha fallado; la entrada cuenta como una sola factura.',
        ],
        'action' => [
            'reset' => 'Restablecer la división',
            'reset_confirm' => 'La división tarda más de lo habitual. ¿Restablecerla e intentarlo de nuevo?',
            'open' => 'Revisar límites',
            'split' => 'Dividir',
            'dismiss' => 'Es una sola factura',
            'dismiss_confirm' => '¿Mantener la entrada como una sola factura? Después continúan el traspaso y el pago.',
        ],
        'dialog' => [
            'intro' => 'Marque las páginas en las que empieza una nueva factura. Cada factura se convierte en una entrada propia con su propio PDF parcial; el original no se modifica.',
            'page' => 'Página :page',
            'starts_here' => 'Aquí empieza una nueva factura',
            'first_page' => 'Primera factura',
            'no_image' => 'Sin vista previa',
            'images_missing' => 'Faltan las imágenes de vista previa — aun así puede marcar las páginas.',
            'signals' => 'Indicios',
        ],
        'signal' => [
            'page_one' => '«Página 1 de …»',
            'continued' => 'página de continuación',
            'new_number' => 'nuevo número de factura',
            'same_number' => 'mismo número de factura',
            'new_sender' => 'nuevo remitente',
            'after_total' => 'tras un bloque de totales',
            'new_letterhead' => 'otro membrete',
        ],
        'blocked' => [
            'split' => 'La entrada se dividió como PDF agrupado — se procesan sus partes.',
            'part' => 'Esta entrada ya es parte de un PDF agrupado dividido.',
            'no_bundle' => 'No hay una comprobación de PDF agrupado abierta para esta entrada.',
            'pending' => 'Las páginas todavía se están comprobando.',
            'running' => 'La división ya está en curso.',
            'too_large' => 'El PDF es demasiado grande para dividirlo.',
            'status' => 'Solo se pueden dividir entradas abiertas (recibidas o con consulta).',
            'transferred' => 'La entrada ya se ha traspasado a la contabilidad — divídala allí.',
            'payment' => 'La entrada está en una remesa de pagos.',
            'linked' => 'La entrada tiene retenciones o vínculos (activo fijo, partida abierta, reclamación, abono).',
            'hold' => 'La entrada está sujeta a una retención legal.',
        ],
        'error' => [
            'too_many_parts' => 'Como máximo :max facturas por PDF agrupado.',
            'parts_too_large' => 'Los PDF parciales serían demasiado grandes en conjunto — divida el archivo antes de subirlo.',
            'stalled' => 'La división no terminó y se ha restablecido.',
            'invalid_page' => 'Los números de página no coinciden con el documento.',
            'single' => 'Marque al menos otra página de inicio — de lo contrario sigue siendo una sola factura.',
            'original_missing' => 'Falta el original en la gestión documental.',
            'original_changed' => 'El original guardado ya no coincide con el hash de la entrada — no se divide.',
            'tool_missing' => 'Falta una herramienta PDF para extraer páginas (mutool, pdftk o poppler).',
            'extract_failed' => 'No se pudieron extraer las páginas :from–:to.',
            'nothing_created' => 'Todas las partes ya existen como entradas — no se ha dividido nada.',
            'failed' => 'La división ha fallado.',
            'original_protected' => 'El original de un PDF agrupado se conserva mientras exista una de sus partes.',
        ],
        'flash' => [
            'reset' => 'División restablecida.',
            'done' => 'PDF agrupado dividido en :count facturas.',
            'queued' => 'División en :count facturas iniciada — las entradas aparecerán en breve en la lista.',
            'dismissed' => 'Mantenido como una sola factura.',
        ],
        'parts' => [
            'title' => 'Partes de este PDF agrupado',
            'intro' => 'El original no se modifica; cada parte es una entrada propia con su propio PDF parcial.',
            'pages' => 'Páginas :from–:to',
            'protected' => 'El original no se puede eliminar mientras existan partes.',
        ],
        'part_of' => 'Parte de un PDF agrupado, páginas :from–:to.',
        'open_original' => 'Abrir original',
    ],
    'girocode' => [
        'title' => 'GiroCode',
        'pending' => 'El GiroCode se está leyendo en segundo plano.',
        'status' => [
            'found' => 'GiroCode leído (página :page).',
            'none' => 'No se ha encontrado ningún GiroCode.',
            'unreadable' => 'GiroCode no legible.',
            'invalid' => 'Código QR con identificador GiroCode, pero con contenido no válido.',
        ],
        'reason' => [
            'page_size' => 'Las dimensiones de página no se pueden leer o son demasiado grandes para rasterizar.',
            'changed' => 'El original guardado ya no coincide con la entrada.',
            'tool_missing' => 'Falta la herramienta de lectura (zbarimg) en el servidor.',
            'too_large' => 'El archivo es demasiado grande para leerlo.',
            'scan_failed' => 'Se produjo un error durante la lectura.',
        ],
        'field' => [
            'name' => 'Beneficiario',
            'iban' => 'IBAN',
            'bic' => 'BIC',
            'amount' => 'Importe',
            'reference' => 'Concepto',
        ],
        'iban_applied' => 'Se ha adoptado el IBAN del GiroCode porque el documento no indicaba ninguno.',
        'finding' => [
            'new_bank_details' => 'El IBAN procede solo del GiroCode; para el proveedor no hay ninguna cuenta bancaria registrada con la que compararlo.',
            'iban_differs_invoice' => 'El IBAN del GiroCode difiere del IBAN de la factura.',
            'iban_differs_supplier' => 'El IBAN del GiroCode no pertenece a ninguna cuenta bancaria registrada del proveedor.',
            'multiple_codes' => 'El documento lleva varios GiroCodes con IBAN distintos.',
            'iban_invalid' => 'Los dígitos de control del IBAN del GiroCode no son correctos — no se ha adoptado el IBAN.',
            'amount_differs' => 'El importe del GiroCode difiere del importe bruto.',
            'name_differs' => 'El nombre del beneficiario del GiroCode no coincide con el proveedor.',
            'unreadable' => 'GiroCode no legible.',
            'invalid' => 'GiroCode no válido.',
        ],
        'blocking' => 'Datos bancarios distintos — un patrón de fraude conocido. Bloqueado en la remesa de pagos hasta que alguien con permiso de autorización confirme la diferencia.',
        'confirmed' => 'Diferencia confirmada el :date.',
    ],
];
