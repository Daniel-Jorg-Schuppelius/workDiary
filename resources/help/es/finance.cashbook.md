---
title: "Libro de caja"
topic: finance.cashbook
version: 1
audience:
    - admin
related:
    - invoices.manage
---

El **libro de caja** documenta ingresos y gastos en efectivo conforme a GoBD
(MVP-414). workDiary no es un sistema de caja (sin TPV, sin obligación TSE).

- **Inmutable**: los registros reciben un número correlativo y una cadena de
  hash; editar y borrar es técnicamente imposible.
- **Anulación en lugar de borrado**: las correcciones son contraasientos con
  motivo obligatorio; el original se conserva.
- **Cierre diario**: arqueo con previsto/contado/diferencia; después quedan
  bloqueados todos los registros hasta la fecha de cierre.
- **Pago en efectivo**: un ingreso puede referenciar una factura — la
  cobertura total la marca como pagada.
- El libro de caja forma parte de la **exportación GoBD Z3** (kassenbuch.csv).
