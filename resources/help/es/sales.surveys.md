---
title: "Encuestas"
topic: sales.surveys
version: 2
keywords:
    - satisfacción del cliente
    - encuesta de satisfacción
    - cuestionario
    - sondeo
    - NPS
    - Net Promoter Score
    - recoger opiniones
    - feedback de clientes
    - encuesta anónima
    - recomendación
audience: []
modules:
    - module.vertrieb
related:
    - contacts.manage
---

Un **motor de encuestas** ligero para NPS y cuestionarios libres — sin
automatización de marketing. Tipos de pregunta: **NPS (0–10)**, escala (1–5),
selección, texto libre. La participación va por un **enlace de un solo uso**
(válido 30 días), sin login del portal.

## Tres reglas obligatorias

- **Protección antifatiga:** como máximo una invitación por dirección de
  correo en 90 días — en **todos** los cuestionarios. El disparador
  automático salta en silencio, el envío manual se rechaza con mensaje.
- **Opt-out por cliente:** quien se opuso no vuelve a ser invitado.
- **El anonimato es una propiedad de almacenamiento:** en cuestionarios
  anónimos la respuesta no lleva referencia a la invitación y la invitación
  ningún momento de respuesta — un join de reidentificación no tiene campos.
  Por eso el ajuste ya no puede cambiarse tras la primera invitación.

## Disparadores

Manualmente por cliente — o automáticamente **tras el cierre del ticket**
(activable en el cuestionario). Un intento de invitación fallido nunca
impide el cambio de estado del ticket.

## Evaluación

**Puntuación NPS** = %promotores (9–10) − %detractores (0–6). Sin respuestas
no hay puntuación — sin valor significa «nada que calcular», no cero. La CSAT
de tickets (valoración en el portal) sigue independiente.

## Registrar una oposición

El opt-out se establece en el cliente: en el diálogo del cliente, en **Portal y otros**, marque **Sin encuestas**. La persona invitada también puede oponerse por sí misma: cada invitación a un cliente y la página de la encuesta incluyen el enlace **Darse de baja de las encuestas**. Conduce, sin iniciar sesión, a una pregunta de confirmación; tras confirmar, la casilla queda marcada en el cliente y la operación consta en el historial de cambios del cliente. El enlace también funciona si la encuesta ya fue respondida o ha caducado. Las invitaciones sin vínculo con un cliente no incluyen enlace de baja. En la exportación e importación de clientes el valor está en la columna `survey_opt_out`.

## Editar un cuestionario

**Editar** en la página del cuestionario cambia el título y el texto de
introducción. El anonimato solo se puede cambiar hasta la primera invitación;
después se mantiene tal como se prometió a los participantes.
