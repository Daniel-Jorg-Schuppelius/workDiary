---
title: "Comisiones"
topic: commissions
version: 1
audience:
    - admin
    - buchhaltung
related:
    - invoices.manage
    - finance.reconciliation
---

Las comisiones nacen de facturas **pagadas**. Las páginas muestran tres
cosas: las **reglas** (quién recibe qué y cuánto), las **líneas abiertas** y
las **liquidaciones**.

## El único momento en que nace una comisión

Exactamente cuando una factura pasa a **pagada**, sea cual sea la vía
(conciliación bancaria, libro de caja, ajuste de iguala, acción manual).
**Emitida pero pendiente nunca genera comisión.**

No es un detalle: quien comisiona por la emisión paga por una facturación que
quizá nunca llegue, y luego tiene que recuperarla.

## Anulación y abono: reversión, no corrección

Una factura anulada o abonada **no modifica la línea de comisión original**.
Se crea una segunda línea con importes negativos. Dos casos:

- La línea original **aún no está liquidada**: ambas pasan a «revertida» y no
  entran en ninguna liquidación; nunca se comunicó nada. La operación queda
  como rastro documental.
- La línea original está en una **liquidación cerrada**: permanece
  inalterada, porque la liquidación es el documento válido frente a nóminas.
  La línea negativa cae en la siguiente liquidación.

El motivo de esta rigidez: una liquidación cerrada ya se comunicó y quizá se
pagó. Modificarla después equivaldría a falsificar un documento que otra
persona ya ha procesado.

## Liquidaciones

Una liquidación agrupa las líneas abiertas de un periodo. Una vez cerrada es
el documento válido; las correcciones van por la siguiente liquidación, nunca
retocando la anterior.
