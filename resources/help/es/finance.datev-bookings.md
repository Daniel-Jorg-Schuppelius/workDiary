---
title: "Lote contable DATEV"
topic: finance.datev-bookings
version: 1
audience: []
related:
    - finance.transfers
    - finance.reconciliation
    - roles.buchhaltung
    - glossary.core
---

El **lote contable DATEV** entrega facturas emitidas, abonos y
opcionalmente gastos aprobados de un período cerrado como archivo DATEV
verificable (formato V700) a la asesoría fiscal. Antes del primer
exporte, la administración configura la **configuración contable**
(números de asesor y cliente, plan de cuentas, cuentas de ingresos,
claves de impuestos). Flujo: crear el lote como borrador, revisar la
vista previa de los asientos, finalizar (genera el archivo con hash
SHA-256 y marca los comprobantes como entregados) y descargar el CSV.
Un lote finalizado es **inmutable**; una factura no puede entregarse
dos veces. Crear y finalizar requiere el rol de contabilidad.
