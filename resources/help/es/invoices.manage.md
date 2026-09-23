---
title: "Facturas & documentos"
topic: invoices.manage
version: 6
audience: []
modules:
    - module.vertrieb
schema: process
related:
    - contacts.manage
    - projects.manage
    - finance.datev-bookings
    - finance.transfers
    - travel-expenses.manage
---

## Objetivo y contexto

La vista de facturas gestiona facturas locales y documentos
conectados. Qué vía manda depende de la organización y de la
integración de facturación en uso: por periodo emite las facturas
WorkDiary o exactamente un sistema externo — nunca ambos a la vez.

## Requisitos

- Datos maestros revisados: cliente, dirección del destinatario,
  datos fiscales.
- **Periodo de prestación y vínculo de proyecto** de las posiciones a
  facturar.
- El derecho a crear facturas; para reclamaciones de pago, el rol
  financiero correspondiente.

## Procedimiento recomendado

1. Elegir cliente y periodo — el diálogo de creación muestra una
   **vista previa** de las posiciones (número, duración en formato
   horario y decimal, importe, aviso de rezagados).
2. Excluir si hace falta registros sueltos con la casilla — quedan
   abiertos y vuelven en la siguiente pasada.
3. Revisar y completar el borrador; por posición se despliegan los
   **registros de origen** (1,50 h = 1:30 h). En un artículo con peso de
   cobre, la casilla **recargo de cobre** del diálogo de línea añade el
   recargo al precio DEL del día como línea propia.
4. Emitir o enviar — PDF, envío y sincronización externa son salidas
   del mismo estado documentado.
5. Ante impagos usar la **reclamación**: el nivel 1 crea un
   recordatorio de pago como PDF propio con resumen de deuda, cargo
   opcional y plazo; el correo lleva la carta y la factura original.
   No nace ningún documento nuevo.

**Factura electrónica.** La XRechnung se genera en sintaxis UBL; si un
destinatario exige CII, elija en el cliente o al enviar el formato de entrega
«XRechnung (XML, sintaxis CII)». Por Peppol siempre se envía UBL. Sin número
de IVA — por ejemplo como pequeña empresa según el § 19 UStG — basta el número
fiscal de los datos maestros de factura electrónica: se añade también como
identificador del vendedor, que exige la validación del destinatario.

## Ejemplo práctico

A fin de mes contabilidad elige «Müller GmbH» y el mes anterior: la
vista previa muestra 14 posiciones y avisa de dos tiempos rezagados.
Un registro discutido se excluye y pasa automáticamente a la
siguiente pasada — la factura sale sin discusión.

## Errores habituales

- **Cambiar en silencio documentos enviados o entregados:** los
  documentos emitidos, contabilizados o entregados son inmutables —
  los errores van por anulación o corrección.
- **Sobrescribir números o importes** en vez de corregir — destruye
  la trazabilidad.
- **Doble soberanía de facturación:** si un sistema externo lleva la
  facturación, las facturas locales no existen en paralelo a
  propósito.

## Efectos y próximos pasos

Las facturas emitidas alimentan partidas abiertas, reclamaciones y la
entrega contable. Después: revisar cobros y su asignación y crear el
lote DATEV para la asesoría.

## Factura libre sin tiempos

En el diálogo de creación, **«Componer las posiciones manualmente»** está al
mismo nivel que la incorporación de tiempos o consumo de material. El borrador
solo necesita el cliente (opcionalmente proyecto, cliente final y plazo de pago)
y empieza vacío: se puede guardar y completar después, pero no emitir ni enviar
mientras no tenga posiciones; esto vale por igual para emisión, correo, Peppol y
la entrega a Lexoffice. Un doble clic en «Crear borrador» no genera una segunda
factura.

**Artículos, material y servicios.** Una posición es un artículo (con variante
opcional), material o texto libre, por ejemplo «Montaje a tanto alzado» o una
fabricación especial sin orden de fabricación. Descripción, número de artículo,
unidad y precio se congelan como valores del documento; los cambios posteriores
en el maestro de artículos no alteran el documento. Un precio ausente debe
introducirse conscientemente (0,00 se admite como posición gratuita); un precio
en otra moneda nunca se convierte en silencio. Las posiciones de artículo y
texto libre **no mueven existencias**; las entregas se gestionan en
almacén/entrega.

**Facturar entregas de fabricación.** Con el módulo de almacén activo,
«Incorporar entrega» toma entregas realizadas del cliente con destino de
facturación local, cada una completa como una posición con cantidad ligada a la
fuente y el precio de venta de la entrega (no el coste de fabricación). Una
entrega solo puede estar en un borrador a la vez; la emisión la marca como
facturada, quitar la posición, descartar el borrador o una anulación completa
la liberan de nuevo, y el origen sigue visible en el documento. Los abonos
parciales no liberan nada; las existencias no cambian con ninguna operación de
factura.
