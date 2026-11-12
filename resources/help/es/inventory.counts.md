---
title: "Inventario físico"
topic: inventory.counts
version: 4
keywords:
    - recuento de existencias
    - toma de inventario
    - conteo de stock
    - inventario cíclico
    - inventario anual
    - hoja de conteo
    - contar existencias
    - diferencias de inventario
    - contabilizar diferencias
    - clase ABC
audience: []
modules:
    - module.lager
related:
    - inventory.stock
    - warehouses.manage
    - articles.master
---

En esta página realiza inventarios por almacén. Puede abrir un
inventario completo o un recuento cíclico parcial por clase ABC que
solo incluye las variantes pendientes. En la vista de detalle registra
las cantidades contadas por línea, también mediante escaneo; mientras
el inventario esté abierto, los resultados pueden completarse y
modificarse. Contabilizar las diferencias genera asientos de corrección
en el stock y cierra el inventario; esta acción requiere un permiso de
aprobación específico y es irreversible, así que revise los valores
antes de aplicarla.

## Recuento en unidades y aprobación

Si un artículo tiene otras unidades, la cantidad contada puede registrarse en una de ellas por línea en la lista de recuento, al escanear y en el recuento móvil; se guarda en la unidad base. Contar y aprobar son permisos independientes. Si la aprobación de cuatro ojos está activada, las diferencias a partir del umbral no las aprueba quien las contó.

**Contar sin conexión con una unidad:** incluso sin red, la vista móvil cuenta en la unidad elegida (por ejemplo, caja). Al enviarlo, el servidor la convierte a la unidad base, igual que en línea.

## Terminar, reabrir o descartar el recuento

**Terminar el recuento** congela las cantidades contadas para la revisión; después ya no se puede registrar nada. Quien aprueba las diferencias puede **reabrir el recuento** para corregir, contabilizar las diferencias o **descartar el inventario**; un inventario descartado no contabiliza nada. El paso de revisión es opcional; también se puede aprobar directamente desde el recuento.
