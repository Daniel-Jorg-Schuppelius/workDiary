---
title: "Valor del cliente"
topic: reports.customer-value
version: 1
audience: []
related:
    - reports.customer-analysis
    - reports.customer-retention
---

El informe de valor del cliente responde: **¿de qué clientes vive la
empresa, dónde hay riesgo de concentración, qué clientes A están en
riesgo?**

- **Segmentos RFM**: cada cliente recibe puntuaciones por quintiles
  1–5 para **R**ecencia (días desde el último servicio),
  **F**recuencia (días de actividad en el período) y valor
  **M**onetario (ingresos). De ahí surgen los segmentos *Champions*,
  *Leales*, *Por desarrollar*, *Nuevos*, *En riesgo* e *Inactivos*.
- **Concentración**: cuota de ingresos del top 5/top 10 e índice de
  Herfindahl-Hirschman (HHI). Por debajo de 1500 no es crítico; por
  encima de 2500 indica alta concentración.
- **Clientes A en riesgo**: ingresos altos (M ≥ 4) pero sin servicio
  desde el umbral configurado — con la evolución de ingresos de
  12 meses.

Los **ingresos** provienen de los registros de tiempo facturable
(la misma fuente que el informe de rentabilidad); los importes
facturados son solo un valor secundario.
