---
title: "Rentabilidad"
topic: reports.economics
version: 1
audience: []
modules:
    - module.auswertungen_team
related:
    - reports.customer-analysis
    - reports.drilldown
---

La vista de rentabilidad (cálculo posterior) muestra por cliente y
proyecto el margen de contribución en el período elegido: **ingresos**
(tiempos facturables × tarifa + material + gastos facturables, como
proyección — la factura vinculante la lleva el sistema externo) menos
**costes** (tarifa interna × tiempo + coste directo de material y
comprobantes), también como **margen** en porcentaje. Incluye un
ranking top/flop por margen, el tiempo no facturable como indicador de
retrabajo y la comparación plan/real contra los presupuestos del
proyecto. Si falta la tarifa interna de coste, esos tiempos entran con
0 € (marcados con `*`) y el margen resulta demasiado optimista.
Exportable como CSV o PDF; datos financieros solo para usuarios con
permiso de lectura de informes.
