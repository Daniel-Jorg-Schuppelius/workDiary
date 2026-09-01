---
title: "Crear una orden"
topic: diary-entries.create
version: 2
audience: []
schema: process
related:
    - protocols.create
    - time-entries.start
    - projects.manage
    - reports.entry-type-analysis
---

## Objetivo y contexto

Las entradas de orden son el libro de órdenes de WorkDiary: cada
mantenimiento, avería o montaje recibe una entrada con cliente, tipo
y estado. La entrada ancla actas, tiempos y la facturación posterior
— y sus transiciones de estado trazan el ciclo de vida de la orden.

## Requisitos

- Un **cliente** existente (obligatorio), opcionalmente un
  **proyecto**.
- **Tipos de entrada** adecuados (mantenimiento, avería, montaje…) —
  los mantiene la administración.
- El derecho a crear entradas de orden.

## Procedimiento recomendado

1. Abre **«Nueva entrada»** en la barra superior o la acción rápida
   del panel.
2. Registra el **cliente** (obligatorio) y, si procede, el
   **proyecto**.
3. Elige el **tipo de entrada** y describe el **contenido** en una o
   dos frases.
4. Opcional: una **duración prevista** en minutos.
5. Las transiciones de estado pasan después por la **ventana de
   detalle** — sin actualización masiva desde la lista.

![Lista de trabajo del libro de órdenes con contadores de estado y entradas](media/auftraege/arbeitsliste.png)
*La lista de trabajo: contadores de estado arriba y debajo las órdenes con estado y acciones.*

## Ejemplo práctico

Entra una avería por teléfono: la oficina crea en menos de un minuto
una entrada de tipo «avería» con cliente y descripción breve. El
técnico encuentra la orden en su lista, arranca el tiempo sobre ella
y adjunta después el acta.

## Errores habituales

- **Esperar cambios de estado masivos:** las transiciones van a
  propósito una a una por la ventana de detalle — la pista de
  auditoría queda limpia.
- **Usar un cliente «varios»:** sin vínculo real faltan luego
  análisis y facturación.
- **Escribir novelas:** una o dos frases bastan — los detalles van al
  acta.

## Efectos y próximos pasos

Con la entrada existe el ancla para todo lo demás: registrar tiempo,
crear un acta si hace falta y llevar el estado hasta el cierre. El
análisis por tipos mostrará después en qué invierte de verdad su
tiempo el negocio.
