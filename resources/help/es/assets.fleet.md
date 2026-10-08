---
title: "Activos y flota"
topic: assets.fleet
version: 2
keywords:
    - gestión de flota
    - gestión de vehículos
    - inventario
    - equipos
    - préstamo de equipos
    - entrega de herramientas
    - devolución
    - registro de combustible
    - registro de carga
    - mantenimiento
    - reportar avería
    - ciclo de vida
audience: []
modules:
    - module.fuhrpark
related:
    - documents.manage
    - travel-expenses.manage
    - reports.overview
---

Los activos y vehículos representan objetos operativos con su estado,
responsabilidades, documentos e información de mantenimiento. Los
registros de repostaje y de carga completan el historial de consumo.

Registre los datos maestros y los identificadores únicos, asigne la
ubicación o los responsables y mantenga los intervalos de
mantenimiento y los documentos relevantes. Los cambios de estado deben
reflejar el ciclo de vida real.

Antes de eliminar o dar de baja un activo, compruebe si tiene
vinculados mantenimientos, expedientes, trayectos o documentos
abiertos. El historial crítico debe archivarse y no perderse por
sobrescritura.

## Asignación y devolución

Mediante el panel «Asignación / devolución» de la página de detalle del
activo, un equipo se asigna a una persona o a un equipo de trabajo,
opcionalmente con referencia a una orden y una fecha de devolución
prevista. Por cada activo existe como máximo una asignación abierta; un
activo ya asignado o bloqueado por un defecto no puede volver a
asignarse. Con la devolución, el activo vuelve a estar disponible. Si
una asignación supera la devolución prevista, aparece un aviso de
retraso y el escáner de plazos notifica a la persona que lo tiene en
préstamo o a la jefatura de equipo.

## Defectos y bloqueos

En el panel «Defectos / bloqueos» pueden registrarse deficiencias con
su nivel de gravedad. Si está marcada la opción «Bloquear activo (sin
retiro posible)», el defecto abierto impide cualquier nueva asignación
hasta que se resuelva o se dé de baja. Para resolverlo o darlo de baja
es obligatoria una nota de resolución.

## Expediente del objeto (ciclo de vida)

El «Expediente del objeto» reúne todo el ciclo de vida de un activo en
una vista coherente e imprimible: datos maestros, ubicación y sala,
estado del ciclo de vida derivado (en servicio, sustituido o fuera de
servicio), puesta en servicio, retirada de servicio y garantía. Debajo
aparecen los mantenimientos, las asignaciones y devoluciones, los
defectos y bloqueos, las órdenes vinculadas, los protocolos, los
consumos de material, los puntos abiertos y los adjuntos, así como el
historial completo del ciclo de vida.

El expediente es accesible mediante el botón «Expediente del objeto» de
la página de detalle del activo y puede emitirse como documento con la
función de impresión del navegador (añadir «?print=1» abre
directamente el cuadro de diálogo de impresión). El estado del ciclo de
vida se deriva del estado, de la retirada de servicio y de la garantía;
no existe un mantenimiento independiente.
