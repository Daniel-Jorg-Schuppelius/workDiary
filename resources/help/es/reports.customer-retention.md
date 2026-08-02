---
title: "Retención de clientes"
topic: reports.customer-retention
version: 2
audience: []
related:
    - reports.customer-value
    - reports.customer-analysis
---

El informe muestra **qué tan bien retiene la empresa a sus clientes** —
y de qué se alimenta la base de clientes.

## Leer la matriz de cohortes

Los clientes se agrupan por su **año de primer servicio** (a nivel de
organización, independiente del filtro de período). Cada fila es una
cohorte, cada columna «+n» el año n posterior. Ejemplo: fila
**2028 (n=12)**, columna **+2** = 75 % → de los 12 clientes llegados en
2028, 9 también compraron servicios en 2030. Si una fila cae rápido, los
clientes se pierden poco después de su llegada. **Un clic en fila o
celda** abre la lista nominal de la cohorte.

## Puente de la base de clientes — definiciones

«**Activo**» en una fecha significa: servicio dentro del umbral
configurado antes de esa fecha (365 días por defecto, filtro «Perdido
tras»). El puente cuadra exactamente:

Base al inicio **+ clientes nuevos** (primer servicio en el período)
**+ recuperados** (antes inactivos, de nuevo activos)
**− nuevos otra vez inactivos** (primeros pedidos sin continuidad)
**− perdidos** (activos al inicio, no al final)
= base al final.

Un clic en un paso del puente salta a la lista nominal de abajo; cada
nombre lleva al informe de clientes y proyectos.

## Indicadores

- **Tasa de retorno**: parte de los clientes activos el año pasado que
  también están activos este año — el indicador más honesto.
- **Antigüedad media del cliente**: años desde el primer servicio,
  promediados sobre los clientes activos al final.

## Qué hacer con ello

- La cohorte se hunde en el año +1 → revisar onboarding / segundo pedido.
- Se acumulan clientes perdidos → recopilar causas (precio, calidad,
  interlocutor), iniciar recuperación dirigida.
- Tasa de retorno por debajo de ~70 % en negocio recurrente → activar
  medidas de fidelización (contratos de mantenimiento, citas periódicas).
