<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : resale_import.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Registro de reventa (funcionalidad 152): mensajes de los lectores de importación (CSV Telekom,
// XLSX/PDF Quality Hosting, lista genérica). Solo nombres de archivo, nunca rutas.
return [
    'column' => [
        'company' => 'Empresa',
        'product' => 'Producto',
        'start' => 'Inicio',
    ],
    'file' => [
        'unreadable' => 'Archivo no legible: :file',
        'xlsx_unreadable' => 'Archivo XLSX no legible: :file (:reason)',
        'no_header' => 'CSV sin fila de encabezado: :file',
        'no_sheet' => 'XLSX sin hoja de cálculo: :file',
        'too_large' => ':file es demasiado grande para la importación (como máximo :rows filas por hoja y :mb MB descomprimidos).',
        'missing_columns' => 'Faltan columnas obligatorias: :columns',
    ],
    'row' => [
        'too_many_fields' => 'Línea :line: :found campos en lugar de :expected (¿separador sin escapar?) - omitida.',
        'missing_company_or_product' => 'Línea :line: falta la empresa o el producto - omitida.',
        'missing_company_or_entitlement' => 'Línea :line: falta la empresa o el entitlement - omitida.',
        'start_unreadable' => 'Línea :line (:company): inicio ":value" no legible - omitida.',
        'end_unreadable' => 'Línea :line (:company): fin ":value" no legible - omitida.',
        'end_before_start' => 'Línea :line (:company): el fin no es posterior al inicio - omitida.',
        'unknown_frequency' => 'Línea :line (:company): periodicidad desconocida ":value" - omitida.',
        'quantity_invalid' => 'Línea :line (:company): cantidad ":value" no legible o no es un entero positivo - omitida.',
        'unknown_currency' => 'Línea :line (:company): moneda desconocida ":value" - omitida.',
        'duplicate_id' => 'Línea :line (:company): identificador ":value" duplicado con la línea :other - omitida.',
        'term_unreadable' => 'Línea :line (:company): duración ":value" no legible - se usa la duración estándar de la periodicidad.',
        'fee_unreadable' => 'Línea :line (:company): cuota ":value" no legible - omitida.',
        'dates_unreadable' => 'Línea :line (:company): fecha no legible (":start" / ":end") - omitida.',
        'contract_end_before_start' => 'Línea :line (:company): el fin del contrato no es posterior al inicio - omitida.',
        'no_contract' => 'Línea :line (:company): sin número de contrato - omitida.',
        'total_unreadable' => 'Línea :line (:company): precio total ":value" no legible - omitida.',
        'contract_start_unreadable' => 'Línea :line (:company): inicio de contrato ":value" no legible - omitida.',
        'status_end_before_start' => 'Línea :line (:company): el fin de contrato derivado del estado no es posterior al inicio - omitida.',
        'status_unknown' => 'Línea :line (:company): estado de contrato ":value" desconocido - tratado como vigente.',
    ],
    'pricelist' => [
        'unreadable' => 'Lista de precios no legible: :file',
        'unreadable_reason' => 'Lista de precios no legible: :file (:reason)',
        'no_sheet' => 'Lista de precios sin hoja "Preisdaten" (falta la columna "Produkttarif").',
        'missing_columns' => 'Faltan columnas obligatorias de la lista de precios: :columns',
        'row_invalid' => 'Lista de precios línea :line (:product): duración, intervalo o precio no legible - omitida.',
        'valid_from_unreadable' => 'Lista de precios línea :line (:product): "Gültig ab" ":value" no legible - validez según la portada o la fecha de importación.',
        'no_valid_from' => 'Lista de precios sin fecha de validez (ni portada ni columna "Gültig ab") - la fecha de importación se toma como inicio de validez.',
    ],
    'invoice' => [
        'unreadable' => 'Archivo PDF :file no legible (:reason).',
        'no_text' => 'No se pudo extraer texto de :file (ni siquiera mediante OCR).',
        'no_number' => 'No se encontró número de factura/abono.',
        'no_date' => 'No se encontró la fecha del documento.',
        'total_mismatch' => 'La suma de las posiciones (:lines) difiere del importe neto del documento (:net) - faltan posiciones o se dividieron de otra forma.',
    ],
];
