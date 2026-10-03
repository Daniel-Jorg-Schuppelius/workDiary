---
title: "Flujo de documentos"
topic: billing.feed
version: 1
audience: []
modules:
    - module.vertrieb
related:
    - billing.chain
    - invoices.manage
    - quotes.overview
    - finance.incoming-invoices
    - travel-expenses.manage
---

El flujo de documentos muestra **todos los documentos en una sola lista**:
presupuestos, facturas de venta y de compra, abonos, comprobantes reflejados
desde una contabilidad conectada y gastos. Las antiguas páginas
«Presupuestos» y «Facturas» llevan aquí: ahora son pestañas de la misma
lista.

**Periodo:** la lista sigue el filtro de fechas del encabezado. Si falta un
documento, compruebe primero el periodo seleccionado.

**Pestañas:** «Todos», «Presupuestos», «Facturas de venta», «Facturas de
compra», «Abonos» y «Gastos» son filtros guardados, no páginas
independientes. El número de la pestaña indica los documentos del periodo.
«Otros» (confirmaciones de pedido, albaranes, varios) solo aparece si
contiene algo. La búsqueda y los filtros se mantienen al cambiar de pestaña.

**Indicadores:** los mosaicos se calculan sobre todo el conjunto filtrado, no
solo sobre la página visible – por moneda y sin conversión.

- **Ingresos**, **Gasto (externo)** y **Saldo** comparan documentos emitidos
  y recibidos.
- **Mis gastos** indica sus propios gastos y la parte que sigue en revisión.
- **Pendiente** y **de los cuales vencidos**: lo vencido es un subconjunto de
  lo pendiente, los dos importes no se suman. Un clic en el mosaico filtra
  los documentos vencidos.
- **Sin efecto monetario:** presupuestos, confirmaciones de pedido y
  albaranes solo cuentan como cantidad.

**Filtros:** la búsqueda encuentra número, cliente y proveedor. Además están
el origen (creado en WorkDiary o procedente de un sistema conectado), la
asignación (cliente o proveedor), el estado (Borrador, Pendiente, Cerrado,
Anulado), «Solo vencidos» e «Incluir archivados». El sentido solo se elige en
«Todos» y «Abonos», porque las demás pestañas ya lo fijan. En la pestaña
«Gastos», «Solo sin comprobante» muestra los gastos que aún no tienen
comprobante asociado; la administración alterna allí entre «Míos» y «Todos».

**Filas:** el número lleva adonde se gestiona la operación – la factura, el
presupuesto, la factura de compra o el justificante del gasto. Los
comprobantes reflejados sin página propia no tienen enlace. En los documentos
pendientes, la columna «Vence» indica los días de retraso y el nivel de
reclamación alcanzado. Sus propias facturas vencidas se reclaman
directamente desde la fila con «Reclamar».

**Nuevos documentos:** arriba a la derecha crea un presupuesto o una factura,
o convierte un archivo de factura en factura electrónica. «Por facturar y por
hacer seguimiento» abre la cadena de documentos con todo lo que sigue
pendiente.

**Visibilidad:** el flujo solo muestra lo que sus permisos en cada área ya
permiten. Si le falta el permiso de presupuestos, por ejemplo, faltan la
pestaña y sus filas.
