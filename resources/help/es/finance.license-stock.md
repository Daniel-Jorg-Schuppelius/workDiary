---
title: "Existencias de licencias"
topic: finance.license-stock
version: 1
keywords:
    - gestión de licencias
    - claves de licencia
    - número de serie
    - clave de activación
    - licencias de software
    - product key
    - vender licencia
    - paquete de licencias
    - reventa de licencias
    - punto de pedido
    - importar claves
audience: []
modules:
    - module.reselling
related:
    - finance.resale
---

Las **existencias de licencias** gestionan las licencias que usted compra
por paquetes y revende individualmente, por ejemplo diez licencias VPN, cada
una formada por varias claves que van juntas. Siempre se cuentan licencias,
nunca claves.

## Producto y paquete

1. **Crear producto de licencia:** nombre, fabricante y las claves por
   licencia, p. ej. «Número de serie» y «Clave de activación». El **stock
   mínimo** decide cuándo aparece «Reponer»: vacío = nunca, 0 = solo cuando
   esté agotado. Opcionalmente vincule un artículo del catálogo de artículos
   (maestro de artículos o programa de contabilidad conectado).
2. **Crear paquete de licencias:** referencia del paquete, fecha de compra,
   proveedor, cantidad y, opcionalmente, el justificante de compra. Se crean
   tantas licencias numeradas como se compraron, al principio sin claves.
   Las recompras son paquetes nuevos.
3. **Registrar claves:** una a una con «Gestionar claves» o como CSV con una
   línea por licencia (plantilla en el paquete). La vista previa muestra
   líneas y errores; solo se aplica un archivo sin errores y solo después de
   su confirmación.

## Estado de una licencia

- **Incompleta:** falta al menos una clave; no se puede vender.
- **Disponible:** todas las claves presentes, no vendida, no bloqueada.
- **Vendida:** asignada a un único cliente, con el juego completo de claves.
- **Bloqueada:** retirada de la venta con un motivo.

Las compradas son siempre la suma de disponibles, vendidas, incompletas y
bloqueadas. El stock es actual y no depende del período de la cabecera.

## Vender y corregir

- **Vender licencia** propone la licencia disponible más antigua. La venta se
  documenta, pero no crea ninguna factura; un número de factura es solo una
  referencia. Con un cliente final como titular, el destinatario de la
  factura sigue siendo el cliente.
- **Corregir venta** asigna la misma licencia sin interrupción a otro
  cliente; ambos quedan en el historial.
- **Anular venta** bloquea la licencia, porque su clave podría haberse usado
  ya. Solo se puede liberar con un motivo y su confirmación.

## Proteger las claves

Las claves se guardan cifradas. Las listas, la ficha del cliente y los
formularios nunca las muestran; el texto claro solo aparece mediante «Mostrar
claves», con el permiso propio «mostrar claves de licencia en texto claro»,
que no se asigna automáticamente a ningún rol. Cada acceso queda registrado,
sin el valor.
