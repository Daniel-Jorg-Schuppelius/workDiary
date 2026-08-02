---
title: "Valor del cliente"
topic: reports.customer-value
version: 2
audience: []
related:
    - reports.customer-analysis
    - reports.customer-retention
---

El informe de valor del cliente responde: **¿de qué clientes vive la
empresa, dónde hay riesgo de concentración, qué clientes A están en
riesgo?**

## Cómo leer este informe

- **Ingresos por cliente (Pareto)**: columnas descendentes + línea
  acumulada — cuanto antes la línea alcanza el 80 %, mayor es la
  dependencia de pocos clientes.
- **Ingresos por inactividad**: más a la derecha = más tiempo sin
  servicio; los puntos a la derecha **por encima** de la línea P80 son
  los clientes A en riesgo.
- **Clientes por segmento**: un clic en una barra filtra la lista de
  clientes de abajo exactamente a esos clientes.
- **Lista de riesgo**: clientes de altos ingresos sin servicio desde el
  umbral configurado, con evolución de 12 meses (sparkline).

## R, F y M — así nacen las puntuaciones

Cada cliente activo en el período recibe tres **puntuaciones por
quintiles de 1 a 5**:

- **R (recencia)** — días desde el último servicio. Cuanto más reciente,
  más alta.
- **F (frecuencia)** — días de actividad en el período.
- **M (monetario)** — ingresos en el período (tiempos facturables, la
  misma fuente que el informe de rentabilidad).

Quintil significa: los clientes se dividen en cinco grupos iguales por
indicador. Ejemplo con cinco clientes por ingresos
10.000/8.000/5.000/1.000/300 € → puntuaciones M 5/4/3/2/1. Las
puntuaciones son **relativas a la propia cartera**, no absolutas.

## Los segmentos (gana la primera regla aplicable)

| Segmento | Regla |
| --- | --- |
| Inactivos | sin servicio en el período |
| Nuevos | primer servicio dentro del período |
| Champions | R ≥ 4 y F ≥ 4 y M ≥ 4 |
| En riesgo | R ≤ 2 con M ≥ 4 (altos ingresos, largo silencio) |
| Inactivos | R ≤ 2 (activo al principio, luego en silencio) |
| Leales | F ≥ 3 |
| Por desarrollar | el resto de clientes activos |

## HHI — concentración con ejemplo numérico

HHI = suma de las cuotas de ingresos **al cuadrado** (en %). Dos clientes
con 50 % cada uno → 50² + 50² = **5000** (extremadamente concentrado);
diez clientes con 10 % → 10 × 10² = **1000** (no crítico). Referencias:
por debajo de 1500 no crítico, 1500–2500 moderado, por encima de 2500
riesgo alto.

## Qué hacer con los segmentos

- **Champions**: retener — servicio prioritario, sin experimentos.
- **En riesgo**: contactar activamente, aclarar el silencio.
- **Por desarrollar**: ofertas dirigidas — ahí está el crecimiento.
- **Nuevos**: completar bien el onboarding, asegurar el segundo pedido.
- **Inactivos**: decidir conscientemente — reactivar o cerrar.
- **HHI/top 5 alto**: priorizar la captación de nuevos clientes.

Cada punto del gráfico y cada fila de la tabla lleva con un clic a su
base de datos (informe de clientes y proyectos o lista filtrada).
