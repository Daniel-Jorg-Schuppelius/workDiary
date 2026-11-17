---
title: "Plan de servicios por cliente y periodo"
topic: service-plan.overview
version: 1
keywords:
    - plan de servicios
    - lista anual
    - lista mensual
    - alcance de servicios
    - no procede
    - documentos recibidos
    - mis periodos
audience: []
related:
    - customers.deadlines
    - diary-entries.follow-up
    - diary-entries.review
    - service-plan.import
---

El **plan de servicios** muestra qué servicios recurrentes reciben sus
clientes y en qué punto están, sin mantener una segunda lista. El estado
procede de las órdenes con **periodo**; qué servicios tiene un cliente figura
en su **alcance de servicios**.

## Dos vistas

- **Vista anual:** las filas son clientes con un servicio anual, las columnas
  los servicios (p. ej. cierre anual, impuesto sobre la renta). Elija las
  columnas en **Servicios** de la barra de filtros; en **Filtros guardados**
  conserva la selección como vista. «Año anterior» y «año en curso» se
  actualizan en las vistas guardadas.
- **Vista por periodos:** las filas son cliente × servicio por mes, trimestre
  o semestre, las columnas los doce meses. Un trimestre abarca tres meses, un
  semestre seis. Delante figuran las características del conjunto de reglas
  de los plazos de clientes (p. ej. periodicidad del IVA, grado de
  dependencia).

## Qué muestra una celda

- Si hay una orden del periodo, la celda muestra su estado: creado,
  aceptado, en curso, en espera, en revisión, hecho.
- Sin orden, un **servicio obligatorio** muestra «falta»; un **servicio
  opcional** no se señala.
- Marque **no procede** con un motivo mientras no haya orden.
- El **vencimiento** procede de la orden, si no del plazo del cliente
  correspondiente; dentro del año, en último término del final del periodo.
  Lo que lo supera se marca en rojo.
- Cada celda guarda **documentos recibidos el** y una **observación**; donde
  el conjunto de reglas lo conoce, también **requerido anticipadamente el**.

Un clic en la celda la abre: **Crear orden** rellena el diálogo de la orden
con cliente, tipo de entrada, grupo de productos, periodo, responsable y
vencimiento; si la orden existe, ábrala desde allí.

## Contadores y filtros

Los contadores cuentan celdas (falta, abierto, en revisión, hecho, no procede,
vencido) y filtran los clientes con esas celdas. Otros filtros: persona
responsable, grupo de clientes, búsqueda por nombre o número de cliente.

## Crear los periodos del año

En la vista por periodos, **Crear los periodos del año** crea las órdenes de
todos los periodos de un servicio, cada una como continuación de la anterior,
con responsable, revisión obligatoria, procedimiento y notas. Las órdenes
existentes y «no procede» se mantienen; los clientes sin predecesor reciben su
primera orden a través de la celda.

## Responsabilidad y sustitución

Responsable es la persona de la orden; sin orden, la persona responsable de
las características de plazos. **Míos** muestra también las celdas de las
personas a las que hoy sustituye. Sin el permiso «Ver el plan de servicios»
solo ve esas celdas. La tarjeta **Mis periodos** del panel y la cola de
trabajo («Periodos vencidos») muestran lo pendiente.

## Plantillas de servicios

Los perfiles sectoriales aportan **plantillas de servicios** (p. ej.
declaraciones y cierre anual, asistencia mensual, control de calidad, visita
de asesoramiento). Al crear un cliente, el diálogo las ofrece como alcance de
servicios.
