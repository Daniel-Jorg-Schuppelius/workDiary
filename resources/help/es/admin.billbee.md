---
title: "Conectar Billbee"
topic: admin.billbee
version: 1
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
---

WorkDiary conecta **Billbee** como agregador multicanal: los pedidos de
Amazon, eBay, Otto, Kaufland, Shopify y otros confluyen en Billbee y se
importan aquí como **réplica de pedidos con origen de canal**.

**Inbox-first:** Los compradores nunca se crean a ciegas como clientes.
Las coincidencias únicas o los compradores recurrentes ya asignados se
vinculan; todo lo demás aparece como propuesta en la bandeja de
integración y se decide allí.

**Credenciales:** clave de API (activada por el soporte de Billbee),
usuario de Billbee y la contraseña de API separada — cifradas por
organización, gestionadas en la tarjeta del plugin (Administración →
Plugins).

**Canal de retorno de existencias:** Si la organización gestiona el
inventario en modo «externo» mediante Billbee, los movimientos locales se
transmiten como **actualizaciones absolutas de existencias** por SKU (sin
deriva en reintentos). Requiere un mapeo de SKU mantenido: los productos
sin equivalente local quedan visibles como asignaciones abiertas.

**Límite:** Billbee permite 2 solicitudes por segundo; la sincronización
respeta este límite automáticamente.
