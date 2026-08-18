---
title: "Facturas y comprobantes"
topic: invoices.manage
version: 2
audience: []
related:
    - contacts.manage
    - projects.manage
    - finance.transfers
    - travel-expenses.manage
---

La vista de facturas gestiona facturas locales y comprobantes
vinculados; qué vía es la líder depende de la organización y de la
integración de facturación empleada. Antes de crear una factura,
verifica cliente, período de servicio, proyecto, posiciones, datos
fiscales y dirección del destinatario. Los borradores pueden
completarse, pero los comprobantes enviados, contabilizados o
entregados externamente no deben modificarse en silencio; en caso de
error usa el proceso de anulación o corrección previsto en lugar de
sobrescribir números o importes.

Desde MVP-462 el diálogo de creación muestra una **vista previa** de
las posiciones generadas (número, duración en formato reloj y decimal,
importe, aviso de registros tardíos) en cuanto se seleccionan cliente
y período. Los registros individuales pueden **excluirse** del ciclo
mediante casilla — permanecen abiertos y reaparecen en el siguiente
ciclo. En la factura, los **registros de tiempo de origen** de cada
posición son desplegables; las cantidades de horas aparecen también en
formato reloj (p. ej. 1,50 h = 1:30 h).

**Carta de reclamación:** al reclamar se genera un PDF de carta de
reclamación propio (nivel 1 = recordatorio de pago) con resumen de la
deuda, gastos de reclamación opcionales y plazo de pago; el correo
lleva la carta y la factura original como adjuntos. No se crea ningún
documento contable nuevo.
