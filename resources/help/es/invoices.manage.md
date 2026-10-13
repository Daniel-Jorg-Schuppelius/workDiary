---
title: "Facturas & documentos"
topic: invoices.manage
version: 9
keywords:
    - crear factura
    - emitir factura
    - factura de venta
    - facturación
    - anular factura
    - factura rectificativa
    - abono
    - factura electrónica
    - XRechnung
    - Peppol
    - enlace de pago
    - pago en línea
    - Stripe
    - Mollie
    - SumUp
    - documentos relacionados
    - cadena de documentos
    - transferir factura a Lexoffice
    - número provisional
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

## Pago en línea

Si hay un proveedor de pagos (Stripe, Mollie o SumUp) configurado como
plugin, las facturas emitidas llevan un enlace de pago con código QR; la
misma dirección aparece en el correo mediante el marcador {{payment_link}}
y en el portal del cliente. El enlace pasa siempre por workDiary: la
página de pago del proveedor solo se crea al abrirlo, por el importe
pendiente en ese momento, de modo que un código impreso sigue siendo
correcto tras pagos parciales.

Cuando el proveedor confirma el pago, la factura se considera pagada o
pagada parcialmente, igual que con un movimiento bancario asignado; un
reembolso lo revierte. Con la contabilidad local, el pago aparece en la
bandeja de contabilización (dinero en tránsito contra el crédito, la
comisión del proveedor como gastos bancarios y de pago); el abono
posterior del proveedor se contabiliza contra la cuenta de dinero en
tránsito. El enlace en la factura y en el correo se desactiva en la
configuración de la organización, en «Pago en línea»; en el portal del
cliente se mantiene.

## Documentos relacionados

Al final de la página, **Documentos relacionados** muestra todos los
documentos vinculados a este: la oferta y sus versiones, facturas, anticipos y
factura final, abonos y anulaciones, albaranes, órdenes de fabricación y
traspasos. Si un programa de contabilidad ha asumido un documento, su
contraparte aparece allí con el número del programa. También aparecen las
relaciones que comunica el propio programa, por ejemplo un anticipo creado en
Lexoffice. La columna **Relación** indica qué es el documento respecto a este;
«a través de …» nombra el documento por el que existe la relación.

## Número del programa de contabilidad

Si un programa de contabilidad asigna los números de factura (página
**Series numéricas**), el borrador lleva un número provisional y la factura
muestra un aviso. **Emitir** – también el envío por correo de un borrador –
transfiere la factura al programa y adopta su número antes de crear el PDF y
el asunto. Lexoffice y easybill toman la confirmación de pedido o la oferta
como documento anterior. El abono y la anulación se transfieren con
referencia a la factura transferida; en sevDesk y easybill la factura debe
estar en el programa. Si el programa la rechaza, el documento sigue como
borrador y el mensaje indica el motivo. En ese caso, cree las facturas de
anticipo y finales en el programa.
