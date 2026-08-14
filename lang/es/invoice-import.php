<?php

return [
    'action' => 'Convertir archivo de factura en factura electrónica', 'title' => 'Importar archivo de factura', 'eyebrow' => 'Asistente de factura electrónica', 'submit' => 'Leer factura',
    'intro' => 'Las facturas PDF, Word y Excel se leen sin ejecutar macros. Los valores reconocidos deben revisarse antes de emitirlos.',
    'group_source' => 'Documento de origen', 'group_target' => 'Destino y salida', 'group_invoice' => 'Datos de factura', 'group_einvoice' => 'Factura electrónica',
    'file' => 'Archivo de factura', 'file_hint' => 'PDF, DOCX, DOC, XLSX o XLS de hasta 20 MB; se usa OCR para escaneos PDF cuando está disponible.', 'delivery_format' => 'Formato de salida preferido',
    'review_hint' => 'El original permanece sin cambios en el DMS. Los datos reconocidos automáticamente son sugerencias, no una aprobación.',
    'format' => ['pdf' => 'PDF', 'xrechnung' => 'XRechnung (XML)', 'zugferd' => 'ZUGFeRD (PDF híbrido)', 'pdf_xrechnung' => 'PDF y XRechnung (XML)'],
    'default_line' => 'Servicios según la factura original :number', 'source_title' => 'Archivo original de la factura :number', 'source_description' => 'Documento de origen sin cambios de la importación de factura.',
    'success' => 'Archivo leído y creado como borrador. Revise los datos y las líneas.', 'options_title' => 'Datos de factura y factura electrónica', 'options_action' => 'Datos de e-factura', 'options_saved' => 'Datos de factura guardados.',
    'invoice_number' => 'Número de factura', 'currency' => 'Moneda', 'issue_date' => 'Fecha de factura', 'due_date' => 'Vencimiento', 'buyer_reference' => 'Referencia del comprador / ID de ruta',
    'buyer_reference_hint' => 'Sustituye para esta factura la referencia de la ficha del cliente.', 'buyer_reference_create_hint' => 'Opcional por factura; si está vacío se usa la ficha del cliente.',
    'imported_notice' => 'Prellenado desde un archivo de factura', 'imported_detail' => 'Puntuación de reconocimiento: :confidence %. Compare número, fechas, importes, impuesto y líneas con el original.', 'original' => 'Archivo original',
    'preferred_format' => 'Salida preferida:', 'flexibility_hint' => 'PDF, XRechnung y ZUGFeRD siguen disponibles por separado.', 'mail_hint' => 'Seleccione el formato adjunto. Los borradores se emiten automáticamente al enviarse.',
    'error' => ['external_billing' => 'Una aplicación externa gestiona la facturación de este cliente. La importación local está bloqueada.', 'duplicate' => 'Este archivo ya se ha importado.', 'no_text' => 'No se pudieron leer datos de factura.', 'unsupported_format' => 'Se admiten PDF, DOCX, DOC, XLSX y XLS.', 'unreadable' => 'El archivo está dañado o no pudo leerse de forma segura.', 'proforma' => 'Las facturas proforma solo pueden enviarse en formato PDF.'],
    'warning' => ['missing_number' => 'El número de factura no se reconoció de forma fiable.', 'missing_issued_on' => 'La fecha de factura no se reconoció de forma fiable.', 'missing_net' => 'El importe neto no se reconoció de forma fiable.', 'totals_mismatch' => 'Los importes neto, impuesto y bruto reconocidos no son coherentes.', 'duplicate_number' => 'El número reconocido ya existe; se utilizó un número local libre.'],
];
