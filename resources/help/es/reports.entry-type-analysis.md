---
title: "Análisis por tipo de orden"
topic: reports.entry-type-analysis
version: 5
keywords:
    - previsto frente a real
    - desviación plan real
    - tiempo de ejecución
    - duración media
    - tasa de retrabajo
    - exceso de horas
    - comparar tipos de trabajo
    - causas principales
    - análisis de desviaciones
    - estimación de tiempos
audience: []
modules:
    - module.auswertungen_team
related:
    - reports.customer-analysis
    - reports.drilldown
---

El análisis por tipo de orden compara duraciones planificadas y reales
por tipo de entrada (p. ej. mantenimiento, avería, migración) y muestra
la duración media y su dispersión, la desviación plan/real en
porcentaje, la cuota de retrabajo y las causas principales si están
clasificadas. Así identifica tipos de orden con supuestos de
planificación poco realistas o sobreesfuerzo sistemático del equipo,
ambos indicios de necesidades de formación o de ajuste de precios.

La duración planificada de una orden es su **Duración prevista (HH:MM)**; si
está vacía, cuenta la duración del servicio de una orden planificada; si no, la duración de la franja horaria o de la cita.

La columna **Causas principales** muestra, por tipo de pedido, las tres clasificaciones más frecuentes del motivo de retrabajo de los tiempos registrados y de la causa raíz de las reclamaciones del pedido. Cada pedido cuenta una vez; un clic muestra los tiempos y reclamaciones afectados. La exportación CSV también incluye la columna.

## Tiempo de ciclo por tipo de pedido

La sección **Tiempo de ciclo por tipo de pedido** muestra, para los pedidos
cerrados en el período, la mediana de días desde la creación hasta el cierre y
el número de cierres. Los pedidos cancelados no cuentan. El filtro de persona
no se aplica aquí a propósito: se mide por pedido, nunca por persona. La vista
de dirección muestra la misma cifra para los tipos de pedido más frecuentes de
los últimos doce meses y enlaza aquí.
