---
title: "Gestionar proyectos"
topic: projects.manage
version: 3
audience: []
modules:
    - module.vertrieb
schema: process
related:
    - contacts.manage
    - time-entries.start
    - timesheets.manage
    - finance.transfers
---

## Objetivo y contexto

Los proyectos agrupan todo lo que pertenece a una iniciativa:
cliente, duración, responsables, tareas, hitos, tiempos registrados y
reglas de facturación. Son el paréntesis entre registro de tiempos y
facturación — lo que está bien configurado en el proyecto no hay que
corregirlo luego registro a registro.

## Requisitos

- Un cliente existente (ver clientes & proveedores).
- El derecho a gestionar proyectos.
- Para facturar: reglas aclaradas (tarifa horaria, tarifas planas,
  facturable sí/no).

## Procedimiento recomendado

1. Crear el proyecto con **cliente y periodo**.
2. Fijar **responsabilidades y estado**.
3. Planificar **tareas o repeticiones**.
4. Registrar el trabajo y seguir el avance en la vista de detalle.
5. Antes de cerrar, revisar tareas abiertas, tiempos, partes de horas
   y posiciones facturables — solo entonces cerrar.

![Lista de proyectos con cliente, estado y duración](media/kunden/projektliste.png)
*La lista de proyectos: cada proyecto con cliente, estado y duración.*

La pestaña **Tiempos** sobre la lista de proyectos muestra los registros
de tiempo de todos los proyectos en el periodo elegido arriba, sin abrir
cada proyecto. Los registros se agrupan por proyecto; con «Agrupar por»
puede cambiar a fecha o persona. Cada grupo indica el número de registros y
el total de todo el periodo y se puede contraer. Puede filtrar por término
de búsqueda (proyecto, tarea, descripción), cliente, proyecto, empleado,
etiqueta y facturabilidad. Solo administración, contabilidad y quien puede
consultar todos los tiempos ven los registros de otras personas; los demás
ven los suyos. Los tiempos sin proyecto no aparecen aquí.

La misma regla de visibilidad se aplica en cada proyecto (registro de
tiempos, hojas de horas, horas totales), en el expediente de una orden y
en los valores de tiempo de la ficha del cliente: sin acceso a todos los
tiempos, las listas y los totales cuentan solo los registros propios y
llevan la nota «solo los tiempos propios».

## Ejemplo práctico

Para una migración de servidores nace el proyecto «Migración CPD» con
duración, tarifa horaria y dos responsables. Los técnicos registran
sus tiempos directamente en el proyecto; a fin de mes la vista de
detalle muestra de un vistazo qué queda facturable.

## Errores habituales

- **Cerrar demasiado pronto:** un proyecto cerrado no acepta más
  registros — revisar antes tiempos y posiciones abiertas.
- **Cambiar reglas de facturación con efecto retroactivo** esperando
  que los registros antiguos las sigan: las reglas valen hacia
  delante.
- **Registrarlo todo sin proyecto:** sin vínculo faltan luego
  análisis y una entrega limpia a facturación.

## Efectos y próximos pasos

Reglas de facturación y estado del proyecto determinan qué tiempos y
materiales pasan a la entrega. Después: configurar el registro de
tiempos del proyecto y revisar la entrega a facturación al cierre del
periodo.
