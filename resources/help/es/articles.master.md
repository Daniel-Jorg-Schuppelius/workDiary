---
title: "Maestro de artículos"
topic: articles.master
version: 1
audience: []
modules:
    - module.lager
related:
    - articles.lexoffice
    - materials.manage
    - inventory.stock
    - warehouses.manage
---

El maestro de artículos es el catálogo central de todos los productos,
materiales y servicios del cliente y es la base para almacén, compras,
fabricación y venta. Cada artículo lleva datos maestros como número de
artículo, tipo, estado, unidad base, GTIN y precios estándar de compra y
venta; en la vista de detalle gestionas opciones con valores, unidades
adicionales con factor de conversión y variantes concretas, que reciben
automáticamente una SKU. La creación y edición se realizan en un diálogo;
en lugar de borrar, retira artículos y variantes (estado «Retired»),
ya que el borrado se bloquea si existen datos dependientes. Crea las
variantes solo cuando las opciones y sus valores estén completos.
