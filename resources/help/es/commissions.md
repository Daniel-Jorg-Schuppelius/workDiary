---
title: "Comisiones"
topic: commissions
version: 2
keywords:
    - comisión de ventas
    - comisiones comerciales
    - liquidación de comisiones
    - escala de comisiones
    - comisión de intermediario
    - comisión por referido
    - recuperación de comisiones
    - tope de comisiones
    - pago de comisiones
    - agente comercial
    - comisión factura Lexoffice
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
modules:
    - module.vertrieb
related:
    - invoices.manage
    - finance.reconciliation
---

Las comisiones nacen de facturas **pagadas**. Las páginas muestran tres
cosas: las **reglas** (quién recibe qué y cuánto), las **líneas abiertas** y
las **liquidaciones**.

## El único momento en que nace una comisión

Exactamente cuando una factura pasa a **pagada**, sea cual sea la vía
(conciliación bancaria, libro de caja, ajuste de iguala, acción manual,
sincronización de comprobantes del programa de contabilidad).
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

## Tramos, tope, plazo de responsabilidad, pagos parciales e intermediarios

Una regla puede tener **tramos**: cuando la facturación de una persona en el
mes, trimestre o año alcanza un umbral, al nuevo importe se aplica la tasa del
tramo más alto alcanzado. Las líneas ya generadas no se recalculan. Un **tope
anual** limita la comisión por año natural; lo que lo supera se pierde, con una
nota en la línea.

Con un **plazo de responsabilidad**, una comisión solo es pagadera una vez
transcurridos los días y entra en la liquidación de ese periodo: si la factura
se anula antes, desaparece antes de haberse comunicado. Si está marcado **«Ya en
pagos parciales»**, la comisión se genera proporcionalmente con cada cobro en
lugar de esperar al pago completo.

Si se revierte un pago en la conciliación bancaria, WorkDiary abona la comisión
hasta la parte entonces pagada (por completo sin «pagos parciales»), como línea
negativa propia; la línea anterior se mantiene. Si el pago vuelve a entrar, la
comisión se genera de nuevo.

Las comisiones también pueden ir a **intermediarios externos** sin cuenta de
usuario. Se gestionan en «Intermediarios», se asignan en la factura y aparecen
en la liquidación y la exportación junto a los empleados.

## Facturas del programa de contabilidad

También las facturas reflejadas del programa de contabilidad (Lexoffice, sevDesk, easybill, JTL-Wawi o
InvoicePlane) generan comisiones:

- Cuando la sincronización de comprobantes detecta que una factura ya
  reflejada se ha pagado, la comisión nace automáticamente: con el pago
  completo y, con **«Ya en pagos parciales»**, también de forma
  proporcional. La base es el importe neto.
- Si la factura se anula en el programa de contabilidad, WorkDiary revierte
  la comisión. Un abono pagado que se refiere a la factura la reduce. Solo
  Lexoffice facilita la relación entre un abono y su factura; en los demás
  programas, anule el abono a mano.
- Las facturas que ya estaban pagadas al reflejarse por primera vez (por
  ejemplo, el histórico) nunca generan una comisión por sí solas. Aparecen
  en **Facturas pagadas sin comisión** y se asignan allí a mano, con el
  mismo diálogo **Asignar comercial** que las facturas locales.
- Las facturas transferidas de WorkDiary al programa de contabilidad
  conservan su comisión en la factura local; nunca nace dos veces.

Como en las facturas locales, quién recibe la comisión lo determina la
asignación manual o, en su defecto, la persona responsable del lead
convertido del cliente.
