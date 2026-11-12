---
title: "Drilldown del indicador a los casos individuales"
topic: reports.drilldown
version: 3
keywords:
    - detalle de KPI
    - profundizar
    - datos subyacentes
    - lista detallada
    - desglosar un indicador
    - puntos abiertos por cliente
    - protocolos de defectos
    - defectos recurrentes
    - causas
    - exportar informe
    - análisis detallado
    - ver datos de origen
    - drill-down
audience: []
related:
    - reports.customer-analysis
    - reports.entry-type-analysis
    - reports.operations
---

En el **análisis de clientes**, el **análisis de tipos de orden** y el
**análisis de productos**, las cifras son clicables. El drilldown lista
los casos individuales que hay detrás de la cifra, con el período del
encabezado de la página y los filtros activos en el momento del clic. El
encabezado indica el cliente, el tipo de orden o el ámbito y el período;
**Al análisis de clientes**, **Al análisis de tipos de orden** o **Al
análisis de productos** le lleva de vuelta. Las páginas requieren el
permiso «Ver los informes».

## Puntos abiertos

**Desglose: puntos abiertos** muestra los puntos que siguen abiertos: en
el propio cliente, en sus órdenes y proyectos, en las órdenes de un tipo
de orden o en los activos del ámbito elegido (entonces con la columna
**Activo**). Columnas: **Título**, **Estado**, **Gravedad**, **Vence** y
**Asignado**; los puntos modificados más recientemente aparecen arriba.
Un clic en la columna **Escalado** abre la lista con **Solo escalado**:
entonces solo aparecen los puntos bloqueados.

## Protocolos de defectos

**Desglose: protocolos de defectos** lista los protocolos de defectos de
las órdenes del período; en el análisis de clientes está detrás de la
columna **Retrabajo**. Columnas: **Título**, **Estado**, **Momento**,
**Creado por** y **Orden**; un clic abre la orden. Los protocolos más
recientes aparecen arriba.

## Causas y defectos recurrentes

**Detalle: causa (tipo de pedido)** muestra dos listas para una causa del
análisis de tipos de orden: **Retrabajo con este motivo** (fecha, orden,
empleado, minutos) y **Reclamaciones con esta causa raíz** (número,
orden, estado).

**Desglose: defectos recurrentes** indica por activo los avisos de defecto
del período y de los últimos **12 meses**, así como los grados de gravedad.
A partir de tres defectos en doce meses, la fila lleva la marca
**Defecto recurrente**; el nombre del activo abre el expediente del
objeto. Se llega a él mediante la cifra de la columna **Defectos
recurrentes** del análisis de productos: entonces muestra solo los casos de
defectos recurrentes de la fila pulsada, y la cabecera indica el área y
**Solo defectos recurrentes**.

## Exportación

Los puntos abiertos y los protocolos de defectos se exportan como PDF,
CSV o Excel; los defectos recurrentes, como CSV o Excel. La exportación
contiene las mismas filas que la lista e indica el período y los
filtros. La página de causas no tiene exportación.
