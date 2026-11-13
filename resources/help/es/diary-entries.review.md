---
title: "Revisión interna de órdenes"
topic: diary-entries.review
version: 1
keywords:
    - revisión
    - principio de los cuatro ojos
    - aprobación
    - consulta
    - enviar a revisión
    - revisión obligatoria
    - periodo
audience: []
related:
    - diary-entries.edit
    - diary-entries.follow-up
    - carry-forward.notes
---

Las órdenes de un tipo de entrada con **revisión obligatoria** pasan por una
revisión interna antes del cierre: quien las trabaja las envía y una segunda
persona las aprueba o plantea una consulta. Nadie aprueba su propio trabajo.

## Procedimiento

1. **Enviar a revisión**: este paso sustituye a «Cerrar» en la orden. La orden
   queda **En revisión**.
2. La persona revisora **aprueba** o **plantea una consulta** (texto
   obligatorio). La pregunta queda en el historial; la orden pasa a
   **Consulta** y vuelve a quien la trabaja.
3. Tras una consulta, **volver a enviar**. Mientras nadie haya decidido, el
   envío también se puede **retirar**.
4. Tras la aprobación (**Aprobado**) quien trabaja la orden la cierra, por
   ejemplo tras la presentación. Si la aprobación es en sí el cierre, la
   persona revisora usa **Aprobar y cerrar**.

## Quién revisa

Puede revisar quien tenga el derecho **Revisar órdenes** o figure en la orden
como **persona revisora**, nunca quien la envió. Si hay una persona nombrada,
el envío solo le aparece a ella; si no, a todas las que tienen el derecho.

Quien solo figura como persona revisora y no tiene derecho de aprobación
encuentra el envío en el mosaico **Para revisar** y lo aprueba mediante la cola
de revisión; la bandeja de aprobaciones le queda cerrada.

## Dónde aparecen los envíos

- **Para revisar** en el libro de órdenes: la cola de revisión, por
  vencimiento y luego por fecha de envío.
- En la **bandeja de aprobaciones** y en el mosaico **Para revisar** del
  panel. Sus propios envíos nunca aparecen allí.
- En la **cola de trabajo** sus órdenes figuran en orden de trabajo: consulta,
  aprobado, en curso, aceptado.

## Viajes, rondas, rutas y Kanban

Si el trabajo termina fuera de la orden – viaje completado, ronda finalizada,
parada de ruta realizada –, la aplicación envía a revisión una orden que la
requiere en lugar de completarla; el paso figura en el historial. En el tablero
Kanban, enviar, aprobar y consultar se hace en la orden: al arrastrar una
tarjeta a las columnas de revisión o a **Completado** se le indica.

## Revisión obligatoria y periodo

- La revisión obligatoria viene del tipo de entrada. Con el derecho
  **Suprimir la revisión obligatoria de una orden** se puede desactivar o
  activar para una orden antes del envío; queda con motivo en el historial y
  en el registro de auditoría.
- Si el tipo de entrada exige un **periodo** (año, semestre, trimestre, mes),
  es obligatorio al crear la orden. Formato: `2026`, `2026-H1`, `2026-Q1`,
  `2026-03`.
