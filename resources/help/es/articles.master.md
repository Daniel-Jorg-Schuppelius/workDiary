---
title: "Maestro de artículos"
topic: articles.master
version: 6
keywords:
    - artículos
    - crear artículo
    - catálogo de productos
    - código de artículo
    - referencia
    - SKU
    - variantes
    - EAN
    - GTIN
    - unidades de medida
    - código arancelario
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
venta; en la vista de detalle gestiona opciones con valores, unidades
adicionales con factor de conversión y variantes concretas, que reciben
automáticamente una SKU. La creación y edición se realizan en un diálogo;
en lugar de borrar, retire artículos y variantes (estado «Retired»),
ya que el borrado se bloquea si existen datos dependientes. Cree las
variantes solo cuando las opciones y sus valores estén completos.

Para los documentos aduaneros, los artículos indican en «Aduana y exportación»
el código arancelario (de 6 a 11 dígitos; se eliminan espacios y puntos), el
país de origen como código ISO y el peso neto por unidad.

## Almacén y valoración

En «Almacén y valoración» usted define en el diálogo del artículo un **método de valoración** propio; sin selección se aplica el método de la organización. El cambio a FIFO o FEFO crea una capa inicial para las existencias; solo se puede volver al promedio móvil cuando no queden existencias del artículo en capas.

Con un **prefijo de números de serie**, el artículo cuenta sus propios números de serie (prefijo y número correlativo, con dígito de control si se desea); sin prefijo se aplica el rango de numeración de la organización.

Las **unidades** adicionales se aplican en movimientos de almacén, escaneo, pedidos y recepción de mercancía: la cantidad puede registrarse en la unidad y se contabiliza en la unidad base. La propiedad «caducidad obligatoria» exige lote y fecha de consumo preferente en la recepción.

Si un sistema de gestión de mercancías gestiona los números de artículo (página «Rangos de numeración», rango «Artículo»), el número de artículo y el GTIN son de solo lectura en el diálogo; los artículos y variantes nuevos reciben su número con la sincronización.

**Clase fiscal:** en el formulario del artículo registra la clase fiscal (general, reducida, incrementada). Se guarda con el artículo, se toma en la importación y se compara en duplicados y fusiones; el tipo de un documento se sigue fijando en la línea.

**Lugar de la prestación:** Para los servicios, elija en el formulario del
artículo el tipo de lugar de la prestación (§ 3a UStG): **General**, **Servicio
del catálogo** (no sujeto para consumidores fuera de la UE) o **Servicio
relacionado con un inmueble** (impuesto en el lugar del inmueble, para todos
los clientes). Vacío significa el valor por defecto de la organización. La
página de detalle muestra el tipo elegido; la importación CSV lo toma en la
columna `place_of_supply_kind` (valores `general`, `catalog`, `property`,
cabecera p. ej. «Leistungsort»). Cómo determina el supuesto fiscal lo explica
el tema de ayuda **Facturas & documentos**.

## Fabricación

En «Fabricación» elige el **plan de trabajo estándar** del artículo: una versión
publicada de una plantilla de procedimiento activa. Las órdenes y la
planificación de fabricación producen después el artículo con ella, mientras una
orden no elija su propia versión; la planificación de necesidades también la
lee. La sección aparece en cuanto existen versiones de procedimiento publicadas.
