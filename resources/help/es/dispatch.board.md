---
title: "Centro de control: tablero y mapa"
topic: dispatch.board
version: 3
keywords:
    - tablero de planificación
    - planificación de intervenciones
    - kanban
    - vista de mapa
    - mapa de trabajos
    - vista por técnico
    - riesgo SLA
    - despachador
    - planificador
    - sala de control
audience: []
modules:
    - module.planung
related:
    - dispatch.overview
    - tours.manage
    - sla.overview
---

El **Centro de control** muestra de un vistazo las órdenes abiertas y
planificadas de un periodo, como **Tablero** (columnas) o como **Mapa**.
Es una mera vista general: todos los cambios se siguen realizando en la
orden correspondiente.

## Tablero

El tablero agrupa las órdenes del periodo seleccionado, a elegir:

- **Por estado**: columnas según el estado de planificación (Sin
  planificar, Planificado, Confirmado, En ruta, Completado).
- **Por empleado**: una franja por cada empleado asignado.

Cada tarjeta indica el cliente, la franja horaria y el empleado, y marca
las situaciones especiales:

- **Conflicto**: la asignación actual presenta un **conflicto de
  planificación crítico** (p. ej. doble planificación, solapamiento de
  turnos).
- **SLA**: para el cliente hay un ticket de servicio **en riesgo** o
  **incumplido** (riesgo de SLA).

Un clic en una tarjeta abre la orden.

## Mapa

El mapa sitúa las órdenes según su propia ubicación o, si no tienen
ninguna registrada, según la **ubicación del cliente**. El color del
marcador sigue el estado de planificación; las órdenes con **SLA en riesgo
o incumplido** se resaltan en **rojo**. Mediante los filtros pueden
mostrarse de forma específica **solo los riesgos de SLA** o **solo las
órdenes no confirmadas**.

## Deliberadamente no incluido

El centro de control es una mera visualización. La **optimización de
rutas**, el **seguimiento en tiempo real** y la **supervisión permanente
de la ubicación** no forman parte de esta vista por motivos de
protección de datos.
