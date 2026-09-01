---
title: "Lote de asientos DATEV"
topic: finance.datev-bookings
version: 2
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
