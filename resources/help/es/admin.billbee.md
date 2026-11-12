---
title: "Conectar Billbee"
topic: admin.billbee
version: 3
keywords:
    - multicanal
    - pedidos de marketplace
    - pedidos de Amazon
    - pedidos de eBay
    - Shopify
    - tienda online
    - comercio electrónico
    - importación de pedidos
    - sincronización de stock
    - mapeo de SKU
audience:
    - admin
modules:
    - module.lager
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

**Buscar:** el campo de búsqueda sobre el espejo de pedidos encuentra el
número de pedido, el número del marketplace, el nombre o el correo del
comprador. La búsqueda global muestra los mismos pedidos en «Pedidos de
Billbee», para la administración y mientras el plugin esté activo.

**Gestión de existencias:** con el ajuste «Billbee», WorkDiary sigue registrando en su propio diario de almacén y comunica cada nivel como valor absoluto por SKU a Billbee. Pueden cambiarlo las personas autorizadas a configurar el almacén; si otro programa (como JTL-Wawi) ya gestiona las existencias, póngalo primero allí en local. No hay modo de solo lectura, porque Billbee no lleva existencias propias.
