---
title: "Importación de pedidos de Shopware"
topic: admin.shopware
version: 1
keywords:
    - Shopware
    - Shopware 6
    - tienda
    - importar pedidos
    - tienda online
    - integración
    - asignar compradores
    - caso por aclarar
audience:
    - admin
related:
    - admin.plugins
    - admin.integration-inbox
    - admin.billbee
    - privacy.overview
    - admin.scheduler
---

La página **Shopware** lee los pedidos de su tienda Shopware 6 y los muestra
en WorkDiary: número, fecha, comprador, estado e importe. Los compradores se
asignan a clientes existentes, de modo que sus pedidos también aparecen en el
cliente. WorkDiary no escribe nada en la tienda. Encontrará la página en el menú
del sistema (el engranaje **Sistema**) en **Plugins** → **Shopware**, en
cuanto el plugin esté activo.

## Configurar el acceso

1. En Shopware, cree una integración en **Ajustes** → **Sistema** →
   **Integraciones**. Necesita permiso de
   lectura sobre pedidos, clientes del pedido, clientes, líneas, transacciones de
   pago, monedas y estados, o la opción **Administrador**.
2. En la página, abra **Credenciales** en el menú ⋯ e introduzca la **dirección
   de la tienda** (sin /api), el **ID de clave de acceso** y la **clave de acceso
   secreta** de la integración. Si la tienda funciona en su propia red, active
   además **Permitir direcciones privadas**; solo surte efecto si el operador lo
   permite.
3. Pulse **Comprobar conexión**. WorkDiary inicia sesión, lee un pedido de prueba
   y muestra la dirección y la versión de Shopware.

Mientras haya pedidos reflejados, no se puede introducir otra tienda.

## Vista previa y confirmación de la importación

**Comprobar vista previa** muestra los pedidos del periodo de la primera
sincronización (30 días por defecto, ajustable en **Credenciales**) y si su
comprador ya está asignado a un cliente. La vista previa no guarda nada.
**Confirmar importación** habilita la sincronización.

Los nuevos pedidos cancelados solo se incorporan si
**Incorporar pedidos cancelados y reembolsados** está activado. Los pedidos ya
incorporados se actualizan siempre.

## Qué se incorpora

- Número de pedido, fecha, **estado** a partir del estado técnico del pedido en
  Shopware (abierto, en proceso, completado, cancelado) e importe con moneda.
  Shopware registra los reembolsos en el estado del pago: si el último pago
  está reembolsado, el pedido cuenta como **reembolsado**; un reembolso parcial
  no cambia el estado.
- Comprador con nombre, e-mail y empresa, las líneas como texto.
- El número de pedido lleva a la gestión de pedidos de la tienda.

## Asignar clientes y casos por aclarar

Si el comprador ya está asignado a un cliente o es claramente reconocible,
WorkDiary vincula el pedido con él. Si no, se crea un **caso por aclarar** en la
bandeja de integración: asigne allí el comprador a un cliente y la siguiente
sincronización vinculará también sus demás pedidos. La importación nunca crea
clientes nuevos. Los pedidos de invitados se reconocen por la dirección de
e-mail.

## Cuándo se sincroniza

Cada 15 minutos WorkDiary obtiene los pedidos nuevos o modificados desde la
última ejecución y una vez al día todo el periodo. Cambie la frecuencia en
**Trabajos programados**; **Sincronizar ahora** inicia al momento. Una ejecución
interrumpida queda **incompleta**; la siguiente continúa desde el último estado
completo.

## Desconexión y conservación

**Desconectar** detiene la sincronización; los pedidos y las asignaciones de
clientes se conservan. La regla de conservación **Datos de compradores de
pedidos de marketplace** elimina el nombre, el e-mail y la empresa de los
compradores tras el plazo de conservación mercantil; los importes y el vínculo
con el cliente se conservan.

## Errores habituales

- La comprobación falla con 401: el ID de clave de acceso o la clave secreta son
  erróneos.
- La comprobación falla con 403: la integración no tiene permiso de lectura sobre
  pedidos.
- La comprobación indica una dirección interna: la dirección apunta a su propia
  red, pero **Permitir direcciones privadas** está desactivado o el operador no
  lo permite.
- La comprobación o una ejecución falla con 403 aunque los pedidos sean
  legibles: a la integración le falta uno de los permisos de lectura indicados
  arriba, por ejemplo sobre transacciones de pago.
