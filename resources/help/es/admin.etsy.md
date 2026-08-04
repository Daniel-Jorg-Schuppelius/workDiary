---
title: "Conectar Etsy"
topic: admin.etsy
version: 1
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
---

WorkDiary conecta directamente la **tienda Etsy** de la organización
(Open API v3): los pedidos aparecen como **espejo de pedidos**, las
notificaciones de envío con seguimiento fluyen de vuelta y las comisiones y
pagos del ledger de Etsy quedan disponibles para los informes.

**Seller app propia:** Cada organización registra su propia seller app en
etsy.com/developers (aprobación en minutos) y guarda el **keystring** y el
**shared secret** en la tarjeta del plugin. La redirect URI de la app debe
ser exactamente la URL de callback mostrada en el panel (HTTPS, sin
desviaciones). Después «Conectar con Etsy»: la tienda se determina
automáticamente; una tienda solo puede vincularse a **una** organización.

**Inbox-first:** Los compradores nunca se crean a ciegas como clientes.
Las coincidencias únicas o los compradores recurrentes ya asignados se
vinculan; todo lo demás aparece como propuesta en la bandeja de
integraciones. Las compras de invitados sin cuenta de Etsy quedan en el
espejo sin propuesta.

**Webhooks (opcional):** En el portal de webhooks de Etsy registre la URL
mostrada en el panel con los cuatro eventos order.* y guarde el secreto
`whsec_…` en la tarjeta del plugin: los pedidos nuevos aparecen al
instante. Sin webhook todo funciona mediante la sincronización periódica
(que siempre sigue siendo la fuente fiable).

**Notificar envío:** La acción del espejo envía número de seguimiento y
transportista a Etsy (Etsy avisa al comprador). Los transportistas
desconocidos se envían como «other». Cada pedido se notifica como máximo
una vez.

**Atención al plazo:** El refresh token de Etsy caduca 90 días después del
último uso; el chequeo de salud avisa a tiempo, después solo ayuda
reconectar. Etsy no ofrece entorno de pruebas: las pruebas se hacen contra
la tienda real según la API testing policy de Etsy (las tarifas se cobran
de verdad).

*The term "Etsy" is a trademark of Etsy, Inc. This application uses the
Etsy API but is not endorsed or certified by Etsy, Inc.*
