---
title: "Análisis de clientes"
topic: reports.customer-analysis
version: 2
keywords:
    - estadísticas de clientes
    - informe de clientes
    - órdenes por cliente
    - tiempo por cliente
    - tasa de retrabajo
    - tiempo no facturable
    - riesgo de margen
    - KPI de clientes
    - clientes y proyectos
    - horas por proyecto
    - ingresos por cliente
audience: []
modules:
    - module.auswertungen_team
related:
    - reports.drilldown
    - reports.entry-type-analysis
    - reports.billing
---

El **análisis de clientes** muestra por cliente el esfuerzo, el
retrabajo y el tiempo no facturable en el período del encabezado de la
página; requiere el permiso «Ver los informes». **Clientes y proyectos**
desglosa horas e ingresos por cliente y proyecto.

## Análisis de clientes

Columnas de la tabla:

- **Órdenes** del período, **Total** (minutos registrados),
  **Facturable**, **No facturable** y su **Proporción %**; una
  proporción alta es un indicador temprano de riesgo de margen.
- **Retrabajo**: órdenes con un protocolo de defectos en el período.
- **Puntos abiertos** en el cliente, sus órdenes y proyectos, de ellos
  **Escalado** (bloqueados).
- **Media min./orden** y **Tendencia 30 d**: órdenes de los últimos 30
  días menos las de los 30 días anteriores.

Filtros: **Proyecto**, **Empleado** y **Tipo de orden**, además de
**Esfuerzo mínimo (minutos)**, **Ocultar clientes sin valores** e
**Incluir clientes ocultos** (clientes marcados con «Ocultar en las
evaluaciones»).

Destinos de los clics: el nombre del cliente abre la ficha del cliente;
**Órdenes** y **Total**, el libro de órdenes del período. **Retrabajo**,
**Puntos abiertos** y **Escalado** abren el drilldown con los mismos
filtros. Encima se muestran **Horas por cliente (top 20)** (un clic abre
Clientes y proyectos para ese cliente), la entrada de pedidos a lo largo
del tiempo, **Puntos abiertos por cliente (top 15)** y las listas **Top
5 esfuerzo**, **Top 5 retrabajo** y **Top 5 no facturable**. Exportación
como PDF, CSV o Excel.

## Clientes y proyectos

La tabla **Cliente / proyecto** muestra el total por cliente y, debajo,
los proyectos con **Horas** e **Ingresos**, ordenados por horas de mayor
a menor; **Σ h** y **Σ €** aparecen encima. Los ingresos son la suma de
los importes guardados de los registros de tiempo. Los gráficos muestran
**Horas por cliente (top 20)** (un clic filtra la página por ese
cliente) y **Proyectos principales por horas**.

Filtros: **Cliente**, **Proyecto** e **Incluir clientes ocultos**. Quien
puede ver todos los registros de tiempo elige en **Área** entre **Solo
las mías** y **Equipo completo** y filtra por **Empleado**; los demás
ven sus propios tiempos. Exportación como PDF, CSV o Excel.
