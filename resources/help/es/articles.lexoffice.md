---
title: "Productos y servicios de Lexoffice"
topic: articles.lexoffice
version: 2
keywords:
    - Lexware Office
    - artículos Lexoffice
    - sincronizar artículos
    - catálogo de productos
    - lista de precios
    - conflicto de sincronización
    - importar artículos
    - servicios
    - tipo de IVA
audience: []
modules:
    - module.vertrieb
related:
    - articles.master
    - invoices.manage
    - glossary.core
---

Esta página muestra el catálogo de productos y servicios sincronizado
desde Lexoffice como vista de solo lectura: el mantenimiento se realiza
en Lexoffice y una sincronización pull mantiene actualizada la caché
local. Cada entrada muestra denominación, número de artículo, tipo,
unidad, precio unitario neto y tipo impositivo, con búsqueda, filtros
por tipo y estado, y una vista de detalle en diálogo. Con permisos
suficientes puede iniciar la sincronización manualmente, siempre que
Lexoffice esté configurado para la organización.

La estrategia de conflicto de la configuración de Lexoffice (Lexoffice
gana, local gana, revisión manual) se aplica también a la sincronización de
artículos: con «revisión manual», los artículos modificados localmente cuyo
estado difiere en Lexoffice aparecen como conflictos en la lista de
conflictos del almacén (Almacén → Conflictos). Allí decide por artículo si
se mantiene el estado local, se adopta el estado de Lexoffice o se descarta
el conflicto. La sincronización manual informa del número de conflictos
nuevos.
