---
title: "Contabilidad local"
topic: accounting.overview
version: 2
audience:
    - admin
    - buchhaltung
modules:
    - module.finance
schema: process
related:
    - accounting.posting
    - accounting.closing
    - finance.datev-bookings
---

## Objetivo y contexto

La contabilidad local lleva un libro mayor propio dentro de WorkDiary
— para organizaciones sin software contable separado. No sustituye ni
a los plugins contables ni a su soberanía de datos. Tres preguntas se
mantienen estrictamente separadas: **soberanía de facturación**
(¿quién emite facturas?), **soberanía de datos maestros** (¿quién
lleva clientes y proveedores?) y **soberanía de asiento** (¿quién
lleva el mayor?) — por periodo manda WorkDiary o exactamente un
sistema externo.

## Requisitos

- Rol de **contabilidad** o administración.
- La decisión por un perfil: contabilidad de caja (EÜR) o partida
  doble.
- Moneda base, ejercicio e inicio de asientos (fecha de corte).
- Ningún sistema externo con soberanía de asiento en el mismo
  periodo.

## Procedimiento recomendado

1. Abrir **Finanzas → Configurar contabilidad** y elegir el perfil.
2. Fijar moneda base, ejercicio e inicio de asientos.
3. Recorrer el **preflight**: comprueba que la organización pueda
   asentar sin lagunas desde la fecha de corte.
4. **Activar** la contabilidad local solo cuando ningún punto siga en
   rojo.
5. Desde ahí los asientos van por el diario (ver «Asentar»), el
   cierre por la página de cierre.

![Configuración de la contabilidad local con elección de perfil y preflight](media/buchhaltung/buchhaltung-einrichtung.png)
*La configuración: perfil contable a la izquierda, preflight a la derecha — solo se activa sin puntos rojos.*

## Ejemplo práctico

Un pequeño taller rescinde su software contable a fin de año: en
diciembre configura el perfil EÜR, completa el preflight y fija el
inicio de asientos al 1 de enero. Los documentos de diciembre quedan
en el sistema antiguo — desde enero asienta WorkDiary.

## Errores habituales

- **Querer asentar con efecto retroactivo:** los documentos previos a
  la fecha de corte son historia y no se reasientan.
- **Doble soberanía de asiento:** asentar en paralelo en el sistema
  antiguo y en WorkDiary crea dos verdades — el preflight lo impide a
  propósito.
- **Forzar la activación con puntos en rojo** — las lagunas te
  alcanzan en el primer cierre.

## Efectos y próximos pasos

Con la activación WorkDiary pasa a ser el mayor rector desde la fecha
de corte: diario, partidas abiertas y cierre se apoyan en él.
Después: conocer la lógica de asientos y la entrada de documentos
(«Asentar») y planificar el primer cierre mensual.
