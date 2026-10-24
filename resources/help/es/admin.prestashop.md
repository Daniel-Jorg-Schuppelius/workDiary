---
title: "Importación de pedidos de PrestaShop"
topic: admin.prestashop
version: 1
keywords:
    - PrestaShop
    - tienda
    - importar pedidos
    - tienda online
    - webservice
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

La página **PrestaShop** lee los pedidos de su tienda PrestaShop y los muestra
en WorkDiary: número, fecha, comprador, estado e importe. Los compradores se
asignan a clientes existentes, de modo que sus pedidos también aparecen en el
cliente. WorkDiary no escribe nada en la tienda. Encontrará la página en el menú
del sistema (el engranaje **Sistema**) en **Plugins** → **PrestaShop**, en
cuanto el plugin esté activo.

## Configurar el acceso

1. En PrestaShop, active el webservice en **Parámetros avanzados** →
   **Webservice** y cree una clave. Necesita permiso de lectura (GET) sobre
   orders, customers, currencies y order_states.
2. En la página, abra **Credenciales** en el menú ⋯ e introduzca la **dirección
   de la tienda** (sin /api) y la **clave del webservice**. PrestaShop entrega las
   horas sin zona horaria: si la tienda no funciona en la zona horaria de su
   organización, introduzca la **zona horaria de la tienda** (p. ej.
   Europe/Vienna). Si la tienda funciona en su propia red, active además
   **Permitir direcciones privadas**; solo surte efecto si el operador lo
   permite.
3. Pulse **Comprobar conexión**. WorkDiary lee un pedido de prueba y muestra la
   dirección.

Mientras haya pedidos reflejados, no se puede introducir otra tienda.

## Vista previa y confirmación de la importación

**Comprobar vista previa** muestra los pedidos del periodo de la primera
sincronización (30 días por defecto, ajustable en **Credenciales**) y si su
comprador ya está asignado a un cliente. La vista previa no guarda nada.
**Confirmar importación** habilita la sincronización.

Los nuevos pedidos cancelados o reembolsados solo se incorporan si
**Incorporar pedidos cancelados y reembolsados** está activado. Los pedidos ya
incorporados se actualizan siempre.

## Qué se incorpora

- Referencia, fecha, **estado** e importe con moneda. El estado sigue las
  propiedades fijas del estado de pedido de PrestaShop, no su nombre: cancelado
  y error de pago → cancelado, reembolso → reembolsado, enviado (también entregado) → completado, pagado → en proceso; en otro caso, abierto.
- El albarán por sí solo (estado estándar «Preparación en curso») no cuenta
  como enviado. Con varias tiendas, WorkDiary lee los pedidos de todas y consulta
  una a una las monedas de las demás tiendas.
- Comprador con nombre, e-mail y empresa, las líneas como texto.
- No hay enlace a la administración de la tienda: su carpeta es secreta en
  cada tienda y sus enlaces solo valen con inicio de sesión.

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

- La comprobación falla con 401: la clave es errónea o el webservice está
  desactivado.
- La comprobación falla con 403 o una ejecución queda **incompleta**: la clave no
  tiene permiso de lectura sobre uno de los cuatro recursos.
- Las horas se desvían varias horas: falta la **zona horaria de la tienda** o es
  errónea.
- La comprobación falla con 401 aunque la clave es correcta: el servidor web no
  transmite la cabecera Authorization (Apache: CGIPassAuth) o la reescritura de
  URL para /api está desactivada.
