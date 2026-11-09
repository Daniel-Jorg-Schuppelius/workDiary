---
title: "Clasificaciones y reglas obligatorias"
topic: admin.classifications
version: 2
keywords:
    - listas de valores
    - categorías
    - listas desplegables
    - catálogos
    - tablas maestras
    - tipos de pedido
    - tipos de fallo
    - causas
    - campos obligatorios
audience:
    - admin
    - geschaeftsfuehrung
    - personalverwaltung
    - teamleitung
    - user
    - aussendienst
    - callcenter
    - support
related:
    - catalog.entry-types
    - diary-entries.create
    - admin.import
    - glossary.core
---

Las clasificaciones son listas de valores por dominio a nivel de
organización (tipos de pedido, actividades, tipos de fallo, causas,
resultados, prioridades, grupos de producto, etc.); cada una tiene
código, denominación y opcionalmente color, icono y orden. Los valores
predefinidos de la plataforma pueden sobrescribirse, complementarse,
reordenarse o desactivarse por organización, y la importación CSV
permite crear o actualizar muchos valores a la vez (columnas
obligatorias: dominio, código y denominación). Las reglas obligatorias
vinculan un tipo de pedido con un dominio requerido y definen desde qué
fase se exige el dato —al crear, antes de cerrar o antes de firmar— y
si la regla bloquea o solo avisa, con mínimo/máximo de valores,
selección múltiple y una condición JSON.

Otros dominios llegan con los perfiles sectoriales, por ejemplo tipos de
prestación, grados de dependencia y canales de comunicación (cuidados), tipos
de evento (catering) y soportes de carga (transporte). El dominio
**Categorías de activos** amplía las categorías informáticas generales del
formulario de activos: un valor de la organización con código propio aparece
además, uno con el código de una categoría general sustituye su denominación
y uno desactivado la oculta. Una categoría que usa un activo solo puede
desactivarse, no eliminarse.
