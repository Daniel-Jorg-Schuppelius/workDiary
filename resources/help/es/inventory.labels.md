---
title: "Etiquetas y plantillas"
topic: inventory.labels
version: 1
keywords:
    - imprimir etiquetas
    - impresión de etiquetas
    - código de barras
    - código QR
    - etiqueta de almacén
    - etiqueta de artículo
    - etiqueta de número de serie
    - etiqueta de lote
    - pegatina
    - SKU
audience: []
modules:
    - module.lager
related:
    - inventory.stock
    - warehouses.manage
    - articles.master
---

Aquí gestiona plantillas de etiquetas y genera etiquetas imprimibles
para variantes, lotes y números de serie. Una plantilla define el
tamaño de papel (A6, A7, A8), la orientación, el código QR opcional y
los campos mostrados; por organización solo puede haber una plantilla
predeterminada y al marcar una nueva se desmarca la anterior. Crear y
editar se hace en un diálogo y gestionar plantillas requiere permiso de
configuración. La etiqueta se genera como PDF con el código escaneable
(número de serie, lote o SKU); sin plantilla se usa una configuración
estándar simple.
