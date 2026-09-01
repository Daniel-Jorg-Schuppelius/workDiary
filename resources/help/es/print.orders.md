---
title: "Órdenes de impresión (imprenta & copistería)"
topic: print.orders
version: 1
audience: []
modules:
    - module.lager
related:
    - claims.overview
    - documents.manage
---

El perfil sectorial imprenta/copistería gestiona cada orden de impresión
como expediente especializado vinculado a una orden de fabricación:
recepción de datos, comprobación del archivo (preflight), aprobación de
impresión, producción, control de calidad y entrega forman un conjunto
reproducible.

**Archivo & preflight:** El archivo de producción reside en el gestor
documental y se vincula a la orden con su suma de verificación SHA-256. El
preflight distingue errores (que bloquean la aprobación) de advertencias;
una anulación manual exige un motivo y queda auditada. Una nueva versión del
archivo devuelve la orden automáticamente a «comprobación de datos».

**Aprobación:** La aprobación congela formato, material, cantidad, color,
fecha y acabado junto con el hash del archivo en una instantánea de
producción inmutable.

**Producción & CC:** Las máquinas bloqueadas o con inspección/calibración
vencida no pueden arrancar con normalidad. Cantidad buena y merma fluyen por
la orden de fabricación hacia el almacén y el poscálculo. El control de
calidad compara con el estado aprobado y documenta liberación, bloqueo o
retrabajo.

**Entrega & retención:** La recogida exige un justificante de entrega, el
envío usa la logística existente y la venta en mostrador se mantiene con
datos mínimos. Al vencer la retención solo se elimina el archivo del
cliente — orden, instantánea y suma de verificación permanecen como prueba
comercial.
