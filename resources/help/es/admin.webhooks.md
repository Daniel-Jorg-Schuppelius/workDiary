---
title: "Webhooks"
topic: admin.webhooks
version: 2
keywords:
    - URL de retorno
    - callback
    - notificaciones de eventos
    - suscripción a eventos
    - firma HMAC
    - clave de firma
    - automatización
    - integración saliente
    - registro de entregas
    - payload
audience:
    - admin
    - geschaeftsfuehrung
related:
    - admin.notification-rules
    - admin.handbook
    - glossary.core
---

Los webhooks envían notificaciones de eventos salientes a sistemas
externos (p. ej. un ERP, una plataforma de automatización o una
herramienta propia). En cuanto se produce un evento suscrito, WorkDiary
entrega a su URL una carga útil JSON firmada mediante `POST` HTTPS.

Procedimiento típico:

1. **Crear webhook**: introducir la etiqueta y la URL de destino
   (HTTPS).
2. Suscribirse a los **eventos** (casillas de verificación). Solo los
   eventos seleccionados desencadenan un envío.
3. La **Clave de firma** se muestra una única vez en texto claro:
   cópiela ahora. Después solo se guarda cifrada y, si es necesario,
   puede rotarse.
4. Con **Enviar evento de prueba**, verificar la accesibilidad y la
   comprobación de la firma.

## Carga útil

La carga útil es deliberadamente mínima y contiene pocos datos
personales:

```json
{
  "event": "openIssue.assigned",
  "occurred_at": "2026-06-14T12:00:00+00:00",
  "organization": { "id": 1 },
  "data": {
    "subject_type": "OpenIssue",
    "subject_id": 42,
    "title": "..."
  }
}
```

Si lo necesita, complete otros campos a través de la API REST.

## Verificar la firma

Cada entrega incluye las siguientes cabeceras:

- `X-WorkDiary-Signature: sha256=<hmac>`
- `X-WorkDiary-Timestamp: <hora-unix>`
- `X-WorkDiary-Event: <clave-evento>`

El HMAC se calcula sobre `<timestamp>.<body>` con la clave de firma:

```text
expected = HMAC_SHA256(timestamp + "." + raw_body, signing_key)
```

Compare `expected` con el valor de la firma en tiempo constante y
rechace las solicitudes con una marca de tiempo demasiado antigua
(protección contra la repetición).

## Fiabilidad

Las entregas fallidas se reintentan con backoff. Tras varios intentos
fallidos consecutivos, el endpoint se **desactiva automáticamente**;
guárdelo como activo para volver a activarlo. El registro de entregas
de cada endpoint muestra el estado, el código HTTP y el momento de los
últimos intentos.
