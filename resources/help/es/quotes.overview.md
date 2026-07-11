---
title: "Ofertas"
topic: quotes.overview
version: 1
audience: []
related:
    - invoices.manage
---

Las ofertas recorren un ciclo de vida fijo: borrador → aprobación →
envío → decisión del cliente → conversión en una factura. La vista
general filtra por cliente y estado (borrador, aprobada, enviada,
aceptada, aceptada parcialmente, rechazada, vencida).

**Borrador:** Una oferta se crea con cliente, proyecto opcional, plazo
de validez y texto de condiciones. Las partidas (descripción, cantidad,
unidad, precio unitario, tipo impositivo) solo pueden añadirse,
modificarse y eliminarse en el borrador; los totales se recalculan
automáticamente al hacerlo. Algunas partidas pueden marcarse como
opcionales — si el cliente rechaza solo estas, sigue tratándose de una
aceptación completa.

**Aprobación y envío:** Tras la aprobación, la oferta se marca como
enviada. En ese momento se genera un enlace de aceptación para el
cliente: se muestra en texto claro exactamente una vez y solo se
almacena un valor de verificación — el enlace debe por tanto copiarse de
inmediato y enviarse con el mensaje de la oferta (correo electrónico o
carta). Desde el envío, el estado es funcionalmente inmutable; los
cambios se realizan exclusivamente mediante una nueva versión que
referencia a la anterior. La cadena completa de versiones permanece
visible en la oferta.

**Decisión del cliente:** A través del enlace, el cliente puede
consultar la oferta sin iniciar sesión y aceptarla, aceptarla
parcialmente (selección de partidas concretas) o rechazarla. Como
alternativa, el tramitador documenta internamente una decisión
comunicada por teléfono o por escrito, en caso de rechazo con motivo
opcional. Vencido el plazo de validez ya no es posible la aceptación;
las ofertas vencidas, rechazadas o enviadas pueden convertirse en una
nueva versión y ofrecerse de nuevo.

**Factura:** Las ofertas aceptadas o parcialmente aceptadas se
convierten con un clic en una factura en borrador. Se transfieren
exclusivamente las partidas aceptadas; a continuación, la factura
recorre el proceso normal de facturación (revisar, emitir, enviar). En
la oferta quedan enlazadas las facturas surgidas de ella, de modo que el
camino desde la oferta hasta el documento es trazable.

Los borradores de oferta sin envío pueden eliminarse; todo lo posterior
al envío se conserva como historial.
