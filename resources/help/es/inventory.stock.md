---
title: "Existencias y escaneo"
topic: inventory.stock
version: 1
audience: []
related:
    - warehouses.manage
    - inventory.counts
    - inventory.labels
    - articles.master
---

La vista de existencias muestra por almacén las cantidades disponibles,
físicas y reservadas de cada variante, el precio medio móvil, el valor
del stock y el punto de pedido. Con permiso de contabilización
registras movimientos manuales (entrada, salida, reserva, liberación) y
defines stocks mínimos y de aviso por variante y almacén; las salidas a
negativo solo son posibles si las permites expresamente. Los lotes se
gestionan en la lista de lotes, donde pueden dividirse y fusionarse. La
vista de escaneo resuelve un código (número de serie, lote, GTIN o SKU)
y contabiliza directamente una acción. Todos los movimientos se
escriben en el diario continuo y no son reversibles; las correcciones
se hacen mediante contraasientos.
