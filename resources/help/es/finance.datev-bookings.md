---
title: "Lote de asientos DATEV"
topic: finance.datev-bookings
version: 7
keywords:
    - exportación DATEV
    - asesor fiscal
    - gestoría
    - exportar asientos
    - EXTF
    - plan de cuentas
    - SKR03
    - SKR04
    - número de deudor
    - clave de contabilización
    - cuenta de ingresos
    - exportación contable
    - revisión fiscal
    - falta NIF-IVA
audience: []
modules:
    - module.finance
schema: process
related:
    - invoices.manage
    - finance.transfers
    - finance.reconciliation
    - roles.buchhaltung
---

## Objetivo y contexto

El lote de asientos DATEV entrega a la asesoría las facturas
emitidas, abonos y, opcionalmente, los gastos aprobados de un periodo
cerrado como archivo DATEV verificable (formato V700). Principio:
WorkDiary no crea **ninguna** contabilidad, sino un lote de entrega
limpio. Si un software de facturación externo (DATEV o Lexoffice)
lleva las facturas, estas **no** pertenecen al lote local — se
excluyen automáticamente y se muestran en la vista de control.

## Requisitos

La administración guarda una vez la configuración contable de la
organización:

- número de asesor y de cliente,
- plan contable (SKR03 o SKR04) y longitud de cuentas,
- cuenta de ingresos por defecto y cuenta aparte para ventas al 0 % /
  exentas,
- la base del rango de números de deudor,
- la asignación de los tipos (19 %, 7 %, 0 %) a las claves de asiento
  DATEV,
- el indicador de bloqueo (GoBD) y el juego de caracteres
  (habitualmente ISO-8859-1).

El número de deudor puede llevarse por cliente; si falta, se deriva
de forma determinista de la base del rango y del número de cliente.
Crear, finalizar y descargar lotes corresponde al rol de
**contabilidad** (y a administradores); la configuración, a
administradores.

Para la contabilidad local, la sección **Cuentas** incluye además la **cuenta
de descuento por pronto pago** y la **cuenta de baja (pérdidas por créditos
incobrables)**: las contracuentas cuando una partida abierta se compensa con
descuento o se da de baja. Si se dejan vacías, se aplica el valor
predeterminado del plan de cuentas (SKR03 8736 y 2400, SKR04 4736 y 6930). Para las deudas existen la **cuenta
de descuentos obtenidos** (3736 o 5736) y la **cuenta de baja de deudas**
(otros ingresos, 2709 o 4839). Si
existe un plan de cuentas local, la cuenta indicada debe existir en él; si
falta una cuenta efectiva, la página lo indica.

## Procedimiento recomendado

1. **Crear el lote:** elegir el periodo, incluir si procede los
   gastos aprobados — aparece un **borrador** con los documentos
   listos para asentar.
2. **Revisar:** la vista previa muestra el asiento por documento —
   signo debe/haber, cuenta de deudor y de ingresos, clave, número de
   documento, importe bruto — con el total. Los datos maestros que
   faltan aparecen como **aviso**; las claves que faltan, como
   **error** bloqueante.
3. **Finalizar:** solo ahora nace el archivo DATEV; se registra una
   huella SHA-256 y los documentos cuentan como entregados. Un lote
   finalizado es **inmutable**.
4. **Descargar** y facilitarlo a la asesoría.

![Lotes de asientos DATEV con indicadores, configuración y creación de lote](media/buchhaltung/datev-stapel.png)
*La vista de lotes: indicadores, configuración, datos EXTF y «Crear lote».*

## Ejemplo práctico

A principios de mes contabilidad crea el lote del mes anterior: dos
documentos avisan de un número de deudor ausente — tras mantenerlo en
el cliente desaparecen los avisos, el lote se finaliza y el CSV va a
la asesoría con su huella.

## Errores habituales

- **Querer entregar dos veces la misma factura:** los documentos
  finalizados quedan bloqueados — las correcciones van por abono o
  documento corrector en el lote siguiente.
- **Ignorar los avisos:** los datos ausentes afloran si no en la
  asesoría.
- **Esperar justificantes en el lote:** PDF/fotos no forman parte;
  quedan en el expediente y van aparte a la asesoría.

## Efectos y próximos pasos

Cuentan las facturas emitidas y pagadas con fecha de documento en el
periodo; los abonos se forman como asiento inverso. Tras la entrega:
mantener la conciliación de pagos y exportar el periodo siguiente
solo tras su cierre.

## Transmisión e informe adjunto

Tras finalizar, **Registrar transmisión** deja constancia de lo que ocurre con el lote: **transmitido** a la asesoría y allí **confirmado** o **rechazado**. Un lote rechazado se vuelve a transmitir una vez aclarado; las correcciones de documentos siguen yendo en el lote siguiente. Cada paso queda con fecha, persona y nota opcional en el historial del lote, y la lista muestra el estado actual. Las notas, sin datos personales, ya que el historial no se puede modificar.

El **informe adjunto (PDF)** resume el lote para la asesoría: periodo, número de asesor y de cliente, número de asientos y anulaciones, total, suma de control del archivo, totales por cuenta de ingresos y por clave fiscal, y el historial.

## Traspaso DATEV desde el diario

Si WorkDiary lleva el libro mayor, el lote de asientos DATEV se genera a partir
de los asientos firmes del diario (tarjeta **Traspaso DATEV** en **Cierre de
periodos**). Cada asiento se convierte en apuntes DATEV contra una línea de
anclaje – normalmente la cuenta de clientes, proveedores o tesorería –, de modo
que DATEV lo contabiliza una sola vez. Una línea con código de impuesto sale en
bruto con su **Clave fiscal DATEV**; las líneas de impuesto se omiten porque
DATEV contabiliza el impuesto por sí mismo. Con inversión del sujeto pasivo
(§ 13b, adquisición intracomunitaria) el importe queda neto y DATEV contabiliza
el IVA repercutido y el soportado. En una cuenta automática (campo
**Automatismo DATEV** de la cuenta) DATEV calcula el impuesto a partir de la
cuenta y el lote no pone ahí ninguna clave. Si el automatismo no encaja con el
asiento, la clave 40 lo suspende y el impuesto queda como un apunte propio. Un
descuento con corrección de impuesto también sale como un único apunte.

## Ventas sin impuesto

Si una factura no muestra impuesto, el lote la contabiliza según su supuesto fiscal,
igual que la contabilidad: entrega intracomunitaria (SKR03 8125 / SKR04 4125),
prestación de servicios a otro país de la UE (8336/4336), inversión del sujeto
pasivo nacional (8337/4337), exportación (8120/4120), venta no sujeta en un tercer
país (8338/4338). Son cuentas automáticas de DATEV; el lote no indica allí clave
fiscal. Las ventas exentas nacionales permanecen en la cuenta configurada para
ingresos exentos.

## Revisión fiscal antes de finalizar

La vista previa de un borrador muestra la **revisión fiscal** del lote con
enlace al justificante. Los **errores** bloquean **Finalizar**: un apunte con
tipo impositivo pero sin clave BU en una cuenta que no es automática; una
entrega intracomunitaria exenta o inversión del sujeto pasivo en otro país de
la UE sin NIF-IVA del cliente; una cuenta desactivada del plan contable local.
Los **avisos** señalan apuntes cuyo tipo difiere del código de la cuenta
automática y la inversión del sujeto pasivo nacional sin NIF-IVA. Son cuentas
automáticas las que tienen código de impuesto por defecto en el plan contable
local; sin plan local, las de la plantilla del plan configurado. Si un error
es correcto en un caso concreto, una persona con **Liberar la revisión fiscal
antes de la exportación DATEV** lo libera con motivo; la liberación queda en
el registro del lote y vale también si el mismo justificante está en un nuevo
borrador. La revisión no modifica el lote.
