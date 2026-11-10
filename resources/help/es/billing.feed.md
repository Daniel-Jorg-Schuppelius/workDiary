---
title: "Flujo de documentos"
topic: billing.feed
version: 6
keywords:
    - facturas
    - presupuestos
    - facturas emitidas
    - facturas recibidas
    - abonos
    - gastos
    - partidas abiertas
    - reclamación de pago
    - facturas vencidas
    - factura electrónica
    - listado de documentos
    - ingresos
    - filtro de proyecto
audience: []
modules:
    - module.vertrieb
    - module.finance
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
documento, compruebe primero el periodo seleccionado. Un resultado de la
búsqueda global abre la lista en la fecha del documento; ese día sustituye
entonces al periodo del encabezado.

**Pestañas:** «Todos», «Presupuestos», «Facturas de venta», «Facturas de
compra», «Abonos» y «Gastos» son filtros guardados, no páginas
independientes. El número de la pestaña indica los documentos del periodo.
«Otros» (confirmaciones de pedido, albaranes, varios) solo aparece si
contiene algo. La búsqueda y los filtros se mantienen al cambiar de pestaña.

**Indicadores:** los mosaicos se calculan sobre todo el conjunto filtrado, no
solo sobre la página visible – por moneda y sin conversión.

Un clic en **Pendiente** filtra los documentos pendientes (un segundo clic quita el filtro); los mosaicos de sumas no tienen destino.

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

**Documentos reflejados:** si el sistema conectado ya no contiene un documento,
aparece como **Eliminado en el origen**. Entonces no cuenta en ningún indicador
y solo aparece con «Incluir archivados». En documentos en moneda extranjera, bajo
el importe figura el equivalente en la segunda moneda.

**Nuevos documentos:** arriba a la derecha crea un presupuesto o una factura,
o convierte un archivo de factura en factura electrónica. «Por facturar y por
hacer seguimiento» abre la cadena de documentos con todo lo que sigue
pendiente.

**Visibilidad:** el flujo solo muestra lo que sus permisos en cada área ya
permiten. Si le falta el permiso de presupuestos, por ejemplo, faltan la
pestaña y sus filas.

## Comprobantes duplicados y original

**Manda el sistema externo:** si una factura se transfirió a un programa de
contabilidad o el programa asignó su número (Lexoffice, orgaMAX, sevDesk,
easybill), aparece una sola vez en la lista, como comprobante reflejado. Esto
vale para cada programa conectado en cuanto un comprobante de salida lleva el
mismo número y pertenece al mismo cliente: una empresa asigna cada número de
factura una sola vez. Un comprobante con el mismo número pero de otro cliente
no desplaza su factura; tampoco los comprobantes de entrada ni los eliminados
en el origen.

**Gastos:** en cuanto un comprobante de entrada está vinculado a un gasto,
solo cuenta el comprobante en los gastos, tanto con Lexoffice como con
sevDesk, Akaunting, Invoice Ninja y los demás programas con espejo de
comprobantes.

**Totales:** los comprobantes de entrada reflejados cuentan como gasto, los
abonos lo reducen; los borradores y los comprobantes anulados no cuentan.

**Original:** el número de un comprobante reflejado abre el original en el
programa cuando existe una dirección para ello (Invoice Ninja, Akaunting). En
las facturas y abonos de sevDesk, los documentos de easybill y las facturas
de orgaMAX, **PDF original** al final de la fila descarga el PDF directamente
del programa; para ello necesita el derecho de ver comprobantes y, para el PDF
de orgaMAX, además el acceso a la administración. Los comprobantes de sevDesk
sin factura (ticket de caja, comprobante de proveedor) y los gastos de orgaMAX
no tienen PDF.

**orgaMAX y sevDesk:** orgaMAX refleja facturas, facturas de anticipo y
gastos; los gastos figuran como comprobantes de entrada en el gasto. Los abonos
de sevDesk figuran como abonos salientes y reducen la facturación, también los
abonos de comisión y de regalías, porque sevDesk no devuelve la categoría
contable al consultarlos. Las
plantillas recurrentes de un programa no son comprobantes y no aparecen en el
flujo; forman parte de la conciliación de suscripciones y licencias.

## Filtrar por proyecto

El filtro **Proyecto** muestra solo los documentos de un proyecto. Los
documentos de programas de contabilidad no tienen proyecto y quedan fuera con
este filtro.
