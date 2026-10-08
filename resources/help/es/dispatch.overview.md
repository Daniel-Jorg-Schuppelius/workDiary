---
title: "Disposición y avisos de conflicto"
topic: dispatch.overview
version: 2
keywords:
    - planificación de trabajos
    - asignar trabajo
    - planificar técnico
    - doble reserva
    - solapamiento
    - descanso
    - jornada máxima
    - reservar vehículo
    - reserva de vehículo
    - confirmar cita
audience: []
related:
    - diary-entries.edit
    - planning.shifts
    - assets.fleet
---

La planificación determina **quién realiza qué orden y cuándo**, como
complemento de la máquina de estados funcional de las órdenes. Cada orden
tiene un **Estado de planificación**:

- **Sin planificar**: ni programada ni asignada.
- **Planificado**: programada o asignada a un empleado.
- **Confirmado**: la asignación se ha confirmado de forma vinculante.
- **En ruta**: la intervención está en curso.
- **Completado**: la orden está cerrada.

## Avisos de conflicto antes de la confirmación

Antes de confirmar la cita, WorkDiary comprueba la asignación prevista
frente a las reglas existentes de jornada laboral y disponibilidad
(solapamiento con otros turnos u órdenes, descanso, jornada máxima diaria
o semanal, vacaciones y ausencias). Hay dos niveles de gravedad:

- Los **conflictos críticos** impiden la confirmación. Solo pueden
  anularse de forma consciente con una **justificación documentada**; la
  anulación se registra a prueba de auditoría.
- Las **advertencias** son indicaciones y no bloquean.

## Reserva de vehículo

En la orden puede reservarse un vehículo para una franja horaria. Si el
vehículo ya está reservado en el periodo deseado, el sistema impide la
doble reserva. Las reservas de cada vehículo pueden consultarse en la
lista de reservas y anularse.
