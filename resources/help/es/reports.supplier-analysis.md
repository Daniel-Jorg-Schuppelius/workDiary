---
title: "Análisis de proveedores"
topic: reports.supplier-analysis
version: 1
audience: []
related:
    - reports.customer-analysis
    - reports.customer-value
---

El análisis de proveedores es la contraparte de compras del análisis de
clientes y responde: **¿en qué gastamos, de qué proveedores dependemos,
dónde hay deudas abiertas?**

## Cómo leer este informe

- **Gasto por proveedor (Pareto)**: barras descendentes más una línea de
  porcentaje acumulado — la rapidez con la que la línea alcanza el 80 %
  muestra la dependencia de unos pocos proveedores (riesgo de
  concentración en las compras).
- **Gasto por mes**: evolución del gasto de la organización en los
  últimos doce meses, independientemente del periodo seleccionado.
- **Importe abierto por proveedor**: comprobantes de compra aún no
  pagados por completo — las deudas actuales.

## Origen de los datos

El gasto proviene del **espejo de comprobantes de la contabilidad**
(facturas de compra, notas de crédito de compra y comprobantes genéricos
por proveedor). Las notas de crédito reducen el gasto. Los borradores y
los comprobantes anulados no cuentan. Por lo tanto, el informe funciona
**sin el módulo de almacén**.

Si el **módulo de almacén** está activo, se añaden por proveedor los
**pedidos** (emitidos en el periodo) y los **pedidos abiertos** (en
curso).

## Indicadores

- **HHI (concentración)** — índice de Herfindahl-Hirschman sobre el
  gasto: por debajo de 1500 no crítico, 1500–2500 moderado, por encima de
  2500 alto.
- **Cuota top 5** — cuota de los cinco proveedores con mayor gasto; el
  riesgo de concentración empieza en torno al 60 %.
- **Tendencia %** — gasto en el periodo frente al periodo de comparación
  inmediatamente anterior, de igual duración.

Cada fila abre la **página de detalle del proveedor** al hacer clic. El
informe muestra datos financieros y, por lo tanto, solo es visible para
usuarios con permiso de informes.
