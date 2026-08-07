---
title: "Valor del proveedor"
topic: reports.supplier-value
version: 1
audience: []
related:
    - reports.supplier-analysis
    - reports.customer-value
---

El informe de valor del proveedor es la contraparte de compras del valor
del cliente y responde: **¿de qué proveedores dependemos, dónde está el
riesgo de concentración, cuáles son estratégicos, inactivos u
ocasionales?**

## Cómo leer este informe

- **Gasto por proveedor (Pareto)**: barras descendentes más una línea de
  porcentaje acumulado — la rapidez con la que la línea alcanza el 80 %
  muestra la dependencia de unos pocos proveedores.
- **Gasto por inactividad**: cuanto más a la derecha, más antiguo es el
  último comprobante; los puntos a la derecha **por encima** de la línea
  P80 son proveedores de alto gasto que no entregan desde hace tiempo.
- **Proveedores por segmento**: al hacer clic en una barra se filtra la
  lista de proveedores de abajo exactamente a esos proveedores.
- **Lista de riesgo**: proveedores cuya cuota de gasto supera el umbral
  configurado (riesgo de concentración de fuente única), con evolución del
  gasto a 12 meses (sparkline).

## R, F y M — cómo se generan las puntuaciones

Cada proveedor activo en el periodo recibe tres **puntuaciones por
quintil de 1 a 5**:

- **R (Recency)** — días desde el último comprobante. Cuanto más corto,
  más alta.
- **F (Frequency)** — número de días con comprobante en el periodo.
- **M (Monetary)** — gasto en el periodo (comprobantes de compra del
  espejo, las notas de crédito lo reducen).

Quintil significa que los proveedores se dividen en cinco grupos de igual
tamaño por indicador. Por lo tanto, las puntuaciones son **relativas a la
propia base de proveedores**, no absolutas.

## Segmentos

- **Estratégico** — R ≥ 4, F ≥ 4, M ≥ 4 (alto gasto, regular, reciente).
- **Proveedor clave inactivo** — R ≤ 2 con M ≥ 4 (alto gasto pero sin
  comprobantes desde hace tiempo).
- **Proveedor habitual** — F ≥ 3 (aprovisionamiento regular).
- **Ocasional** — todos los demás proveedores activos.
- **Nuevo** — el primer comprobante cae dentro del periodo.
- **Inactivo** — sin comprobantes en el periodo.

El informe muestra datos financieros y solo es visible para usuarios con
permiso de informes.
