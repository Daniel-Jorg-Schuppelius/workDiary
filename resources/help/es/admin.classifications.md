---
title: "Clasificaciones y reglas obligatorias"
topic: admin.classifications
version: 1
audience:
    - admin
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
