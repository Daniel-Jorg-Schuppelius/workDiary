---
title: "Cualificaciones"
topic: catalog.qualifications
version: 3
keywords:
    - competencias
    - habilidades
    - certificados
    - certificaciones
    - formaciones
    - capacitación
    - aptitudes
audience: []
related:
    - planning.shifts
    - catalog.activity-categories
    - glossary.core
---

Las cualificaciones registran competencias y conocimientos del personal,
como certificados, habilitaciones o formaciones; cada cualificación
tiene un nombre único y, opcionalmente, una abreviatura y una
descripción. Cree el nombre (debe ser único) y, si procede, la
abreviatura; la vista general muestra a cuántas personas está asignada
cada cualificación y la ordenación por columnas ayuda con listas
extensas. Las cualificaciones inactivas se conservan pero dejan de
ofrecerse: marca como inactivas las entradas en desuso en lugar de
borrarlas, para preservar el historial de asignaciones.

## Cualificaciones obligatorias: aviso o bloqueo

Las cualificaciones obligatorias se definen en el tipo de turno y en el
encargo (zona «Cualificaciones» de la planificación). Una cualificación solo
cuenta si es válida el día del servicio: si el día es anterior a «válida
desde» o posterior a «válida hasta» de la asignación, se considera faltante.

De forma predeterminada, el plan de turnos, el encargo y la matriz de
cualificaciones solo avisan de la carencia. Si la organización cambia a
**Bloquear la asignación** en «Organización y configuración», estas vías rechazan la
asignación:

- crear un turno o asignarlo a otra persona, otro tipo de turno u otro día,
  incluso si se anula la comprobación de cumplimiento,
- aprobar un intercambio de turno (en ambos sentidos),
- importación de turnos; la rotación deja el servicio libre y lo cuenta como
  omitido,
- asignar un encargo a una persona, incluir un encargo en una ruta y aceptar
  una propuesta de hueco.

Las propuestas de asignación ya no muestran a las personas bloqueadas. Las
asignaciones existentes se mantienen; un servicio así solo puede confirmarse
en la planificación con una justificación, que queda registrada.

Si modifica las cualificaciones obligatorias de un encargo ya asignado a una
persona, WorkDiary comprueba de inmediato a esa persona: en modo bloqueo, el
cambio se rechaza mientras le falte alguna de las cualificaciones; en modo
aviso, se guarda y la carencia se indica como aviso.
