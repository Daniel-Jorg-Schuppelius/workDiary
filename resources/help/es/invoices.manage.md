---
title: "Facturas & documentos"
topic: invoices.manage
version: 17
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
    - consulta sobre factura
    - justificante de servicios
    - hoja de horas
    - documentos justificativos
    - administración pública
    - Leitweg-ID
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
tránsito. Un pago anticipado en línea del alquiler se registra ya en la
fecha de pago como anticipo recibido, con IVA; la factura que lo descuenta
liquida el anticipo y solo muestra como crédito el resto pendiente. El
enlace en la factura y en el correo se desactiva en la configuración de la
organización, en «Pago en línea»; en el portal del cliente se mantiene.

## Anular y facturar de nuevo

Un borrador se anula directamente; una factura emitida, mediante un documento
de anulación con número propio. Ambas vías liberan todo lo que la factura
facturó, igual que la anulación de una transferencia: tiempos registrados,
material, desplazamientos, gastos, partidas de alquiler, entregas y el
complemento hasta el tiempo objetivo del proyecto. Un pago anticipado en línea
del alquiler vuelve a su reserva y se descuenta en la siguiente factura. Las
partidas de la factura anulada no cambian; la siguiente factura del periodo
vuelve a contener las horas y el complemento. Una factura pagada se corrige
con una factura rectificativa. Esta no libera nada, porque puede abonar solo
una parte de la factura.

Una factura emitida que solo un pago anticipado en línea cubre en parte
también se anula mediante un documento de anulación: el pago anticipado vuelve
a su reserva y se descuenta en la siguiente factura. Si otro pago cubre la
factura en parte – un movimiento bancario en la conciliación de pagos
(incluido un descuento por pronto pago aceptado), un pago en caja, un pago en
línea de la factura o una compensación de la partida abierta –, la anulación
sigue bloqueada y la factura indica el motivo. Anule primero ese pago; cuando
la factura esté pagada por completo, corríjala con una factura rectificativa.

Con la contabilidad local, el documento de anulación compensa exactamente la
partida abierta de la factura anulada, tras un pago anticipado el resto
después del anticipo; las demás partidas abiertas del cliente no se tocan.
Después, el anticipo vuelve a estar abierto y se liquida con la siguiente
factura. Por eso, contabilice la factura antes que su documento de anulación;
de lo contrario, el documento de anulación espera en la bandeja de
contabilización con un aviso. Una factura anulada con documento de anulación
también aparece allí para contabilizarla, porque ambos documentos pertenecen
al diario. El documento de anulación no cuenta como pago, tampoco en los
informes por criterio de caja (resultado por caja, IVA por criterio de caja).

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
el asunto. Lexoffice y easybill toman la confirmación de pedido o la oferta como documento anterior; orgaMAX crea la factura a través de un pedido y la bloquea. El abono y la anulación se transfieren con
referencia a la factura transferida; en sevDesk y easybill la factura debe estar en el programa; orgaMAX no asume ninguno de los dos. Si el programa la rechaza, el documento sigue como
borrador y el mensaje indica el motivo. En ese caso, cree las facturas de
anticipo y finales en el programa.

## Consultas sobre facturas

Cuando un cliente responde a una factura por correo, WorkDiary registra al
consultar el buzón una **consulta sobre la factura**: en el historial de la
factura, como nota de comunicación en el cliente y en la sección
**Consultas** de la página de la factura. Se reconocen la respuesta al correo
de factura enviado por WorkDiary y un número de factura o de documento en el
asunto o el texto – también para documentos emitidos por Lexoffice, sevDesk,
easybill u otro programa conectado. Con un número, el remitente debe
pertenecer al expediente (cliente, persona de contacto, cuenta del portal o
destinatario del correo de la factura); de lo contrario el correo queda
marcado en la bandeja de asignación, igual que con varios números. Cada
mensaje se registra una sola vez, las respuestas automáticas de ausencia
nunca.

La lista de trabajo **Consultas sobre facturas** (botón en el flujo de
documentos) muestra las consultas abiertas y respondidas de todas las
facturas y documentos; **Marcar como respondida** cierra una consulta,
**Reabrir** la recupera. Ambas constan en el historial de la factura.

## Documentos justificativos y justificante de servicios

Las hojas de horas y los justificantes de servicios viajan incrustados en la
factura electrónica: en la XRechnung, en el PDF ZUGFeRD y por Peppol. Así lo
exigen las administraciones y Hacienda: un anexo de correo suelto o un enlace
no forma parte de la factura. La tarjeta **Documentos justificativos** de la
página de la factura ofrece tres vías mientras la factura es un borrador:

- **Crear justificante de servicios** genera un PDF a partir de los tiempos
  facturados: por posición la fecha, la persona, la hora, la duración y la
  actividad, además de las conformidades de las hojas de horas firmadas. Si lo
  desea, se añaden las hojas firmadas y una tabla CSV (organización, sección
  **Factura electrónica: documentos y destinatarios**).
- **Añadir documento** admite un archivo propio: PDF, PNG, JPEG, CSV, XLSX u ODS
  sin contraseña. Los destinatarios no aceptan otros formatos.
- **Añadir enlace** solo sirve para portales, como los anexos grandes de OZG-RE.
  A efectos fiscales un enlace no sustituye a un anexo incrustado.

WorkDiary asigna los nombres de archivo (`nachweis-1-…`) para que sean únicos.
**Emitir** fija los documentos; un justificante generado se crea de nuevo con el
número definitivo. Después no se pueden cambiar ni quitar. Si envía en PDF, los
documentos van como archivos propios en el mismo correo.

La factura electrónica indica el momento de la prestación en su parte
estructurada: la fecha o el periodo de las posiciones; si no, la fecha de la
factura. La descripción de los servicios sigue en las posiciones; el
justificante la complementa.

### Clientes de la administración pública

En la ficha del cliente, ponga el **perfil de destinatario de la factura
electrónica** en «Administración pública». Entonces:

- La Leitweg-ID (referencia del comprador) es obligatoria.
- Por correo solo va la XRechnung, sin PDF al lado; el diálogo de envío solo
  ofrece estos formatos. El envío electrónico emite antes el borrador para que
  el XML lleve los documentos fijados.
- El justificante de servicios se adjunta automáticamente al emitir si la
  factura contiene tiempos.
- La factura electrónica con documentos puede ocupar como máximo 10 MB; la
  comprobación avisa si es mayor. El límite se puede cambiar por cliente, igual
  que si el justificante se adjunta siempre o nunca.

Si un programa de contabilidad asigna el número, los documentos se quedan en
WorkDiary: WorkDiary no los transfiere a Lexoffice, sevDesk, easybill ni
orgaMAX. Quien envíe la factura desde el programa los adjunta allí a mano; la
bandeja de integraciones lo recuerda. El traspaso ofrece **Justificante de
servicios** para descargar.

## Liberar retenciones de garantía

Las retenciones aparecen en la página de la factura, en la tarjeta
**Retenciones de garantía**, con tipo, importe, fecha de liberación y estado.
En una factura emitida, el icono del candado abre el diálogo **Liberar**; una
nota deja constancia del motivo. Con la liberación, el importe pasa a ser una
partida abierta normal, vuelve a contar en el importe a pagar y se reclama.
Una retención sustituida por un aval ya se ha pagado: su liberación solo pone
fin a la finalidad de garantía, no se genera ningún crédito nuevo y el
registro de avales indica que el documento debe devolverse. Para liberar se
necesita el permiso de facturación.

## Aprobación y supuesto fiscal del borrador

Con **aprobación obligatoria**, la aprobación vale para el estado revisado: si
después modifica posiciones, condiciones, cliente, fechas o el texto de la factura,
la aprobación se anula y debe concederse de nuevo, no por la persona que creó la
factura. El registro deja constancia de la anulación.

Cada borrador toma su **supuesto fiscal** (inversión del sujeto pasivo, exportación,
exención) de los datos del cliente, también el borrador libre. Si los datos del
cliente cambian después —por ejemplo con el atributo «El destinatario adeuda el IVA»
o un nuevo NIF-IVA—, la siguiente edición del borrador aplica el nuevo supuesto. Si
emite un borrador con un supuesto fiscal desactualizado, workDiary lo ajusta y
detiene la emisión: compruebe los importes y vuelva a emitir.

Con **inversión del sujeto pasivo**, la factura electrónica necesita el NIF-IVA del
cliente (regla BR-AE-02), también en la inversión nacional según el § 13b UStG; la
comprobación previa al envío indica si falta. Todas las posiciones figuran entonces
con un 0 % y la mención «Inversión del sujeto pasivo» en la XRechnung.

El **texto de aviso** sigue al supuesto fiscal: si el aviso automático del supuesto
anterior (por ejemplo, sobre la inversión del sujeto pasivo o la exportación) sigue
sin cambios en el texto de la factura, lo sustituye el aviso del nuevo supuesto o se
elimina. Si modificó el aviso a mano, su texto se mantiene: workDiary se lo indica y
usted lo revisa.

## Motivos de exención en la factura electrónica

Cada categoría de IVA sin impuesto lleva su motivo de exención en la XRechnung y en
el PDF ZUGFeRD, en facturas mixtas por cada desglose: inversión del sujeto pasivo
(AE), entrega intracomunitaria (K), exportación (G) y «no sujeto» (O) con texto y
código; la categoría Z no lleva motivo. «Exento» (E) cita el § 19 UStG solo para
pequeñas empresas. Para otras exenciones, cree en la **matriz de reglas fiscales**
una regla con el tipo «Exento» e indique el supuesto de exención como texto de aviso
del justificante, por ejemplo «Tratamiento médico exento (§ 4 n.º 14 UStG)»; sin
regla solo figura «Operación exenta» y la comprobación previa al envío avisa.

La comprobación previa al envío indica además: falta del NIF-IVA del cliente en
entregas intracomunitarias, falta del NIF-IVA de la organización en entregas a otros
países de la UE y exportaciones, «no sujeto» junto a otras categorías y una posición
de categoría sin impuesto con un tipo superior al 0 %. Con «no sujeto» la factura
electrónica no lleva NIF-IVA; basta el número fiscal. A la factura electrónica le
faltan aún dos datos (aviso): el país de entrega en entregas intracomunitarias y la
omisión del tipo por posición en «no sujeto».

## Nota de exención en el PDF de la factura

Las posiciones sin impuesto también necesitan una nota en la propia factura
(§ 14, apdo. 4, n.º 8 y § 14a UStG). Bajo el desglose del impuesto, el PDF imprime
para cada categoría fiscal el mismo texto que el motivo de exención de la factura
electrónica, por ejemplo «entrega intracomunitaria exenta» para K o la mención de
inversión del sujeto pasivo para una posición aislada con AE. Si el texto de la
factura ya contiene la nota — o la nota automática de esta categoría procedente del
supuesto fiscal —, la línea se omite. La inversión del sujeto pasivo de todo el
documento y el § 19 UStG mantienen su propia línea como hasta ahora. Las facturas
emitidas antes de esta ampliación se reimprimen sin cambios. El abono y la factura
de anulación adoptan la categoría fiscal de cada posición del original.

Si elige una categoría sin impuesto (AE, Z, E, G, K, O) en una posición o en un plan
de facturación, el formulario fija el tipo en 0 %; en cualquier caso se guarda 0 %.

## Destinatario y NIF-IVA en la factura

Una factura emitida imprime el destinatario tal como era al emitirla: el nombre, la
empresa, la dirección y el NIF-IVA del cliente quedan fijados con la emisión. Si
después modifica los datos del cliente, la reimpresión, el PDF adjunto, la
XRechnung, el ZUGFeRD y la exportación GAEB no cambian; solo las facturas nuevas
adoptan la nueva dirección. Un borrador muestra siempre los datos actuales del
cliente. El documento de anulación y la factura rectificativa fijan el destinatario
al emitirse ellos mismos. En cambio, la dirección de entrega electrónica
(identificador Peppol o correo electrónico) y las cartas de reclamación siguen los
datos actuales del cliente, porque se entregan hoy.

Si el cliente tiene un NIF-IVA registrado, aparece en la cabecera de la factura
(«Su NIF-IVA»). En entregas intracomunitarias y en la inversión del sujeto pasivo
es obligatorio (§ 14a UStG). Las facturas emitidas antes de esta ampliación se
reimprimen sin esa línea.

La emisión también fija la indicación de los costes de mano de obra según el
§ 35a EStG: un cambio posterior de la regla de la organización o del tipo de
cliente ya no modifica una factura emitida.
