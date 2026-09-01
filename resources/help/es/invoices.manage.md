---
title: "Facturas & documentos"
topic: invoices.manage
version: 3
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
   **registros de origen** (1,50 h = 1:30 h).
4. Emitir o enviar — PDF, envío y sincronización externa son salidas
   del mismo estado documentado.
5. Ante impagos usar la **reclamación**: el nivel 1 crea un
   recordatorio de pago como PDF propio con resumen de deuda, cargo
   opcional y plazo; el correo lleva la carta y la factura original.
   No nace ningún documento nuevo.

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
