---
title: "Autofacturación"
topic: invoices.self-billing
version: 1
keywords:
    - autofacturación
    - factura emitida por el destinatario
    - abono a proveedor
    - liquidación de subcontratistas
    - código de tipo 389
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
modules:
    - module.vertrieb
related:
    - invoices.manage
    - finance.incoming-invoices
---
En la **autofacturación**, su organización, como destinataria del servicio, emite ella misma la factura por el servicio del proveedor (art. 14, apdo. 2, frase 2 de la Ley alemana del IVA), por ejemplo para subcontratistas, comisiones o compras. El documento se denomina **Gutschrift** y se mantiene separado del abono comercial de una factura propia.

## Requisitos

- En el proveedor consta la fecha del **acuerdo de autofacturación** (editar proveedor). Sin acuerdo, el proveedor no aparece para elegir.
- El proveedor tiene un **NIF-IVA** o un **número fiscal** y una dirección completa.
- El documento tiene al menos una posición y un **período de prestación**.

Si falta algo, la página de detalle indica los puntos pendientes; **Emitir** solo aparece cuando todo está completo.

## Proceso

1. **Crear autofactura**: elegir proveedor y período de prestación.
2. **Añadir posición**: descripción, cantidad, unidad, precio unitario y tipo impositivo. Se calcula igual que en facturas y presupuestos.
3. **Emitir**: asigna el número de su propia serie (por defecto `GS-año-número`, modificable en las series de numeración) y fija importes y contenido.

Al emitir se genera la factura electrónica (XRechnung, código de tipo 389), que llega a **facturas recibidas** ya asignada al proveedor. Desde allí pasa a la contabilidad como cualquier factura recibida: a DATEV o al programa conectado. El PDF lleva la indicación «Gutschrift», nombra al proveedor como prestador y remite al acuerdo.

## Corrección y anulación

Una autofactura emitida no se puede modificar. **Anular** solo es posible mientras la contabilidad no la haya asumido; el registro de entrada correspondiente se rechaza. Después se corrige con una nueva autofactura con importes negativos.

Permisos: ver con «Listar las facturas», crear con «Crear una factura», emitir y anular con «Emitir una factura».
