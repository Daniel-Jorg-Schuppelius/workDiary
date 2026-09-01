---
title: "Reglas de precios y márgenes"
topic: pricing.margin-rules
version: 1
audience:
    - admin
modules:
    - module.lager
related:
    - supplier-catalogs.overview
    - articles.master
---

Las reglas de margen derivan propuestas de precio de venta a partir de
los precios de compra. Garantizan que los precios de los catálogos de
proveedores no tengan que calcularse a mano — y que ninguna aceptación
eluda el cálculo.

**Contenido de la regla:** Una regla calcula o bien con un recargo en
porcentaje sobre el precio de compra o con un margen objetivo en
porcentaje del precio de venta; si ambos están definidos, el margen
objetivo tiene prioridad. Opcionalmente se añaden: un margen mínimo (la
propuesta se marca si quedara por debajo de él), un precio de venta
mínimo y un esquema de redondeo para precios finales comercialmente
redondos. Las reglas pueden desactivarse sin eliminarlas.

**Ámbito de aplicación y orden de aplicación:** Una regla rige de forma
global, para un proveedor, para un grupo de mercancías o para la
combinación de ambos. Si coinciden varias reglas activas, gana la más
específica: proveedor más grupo de mercancías antes que solo uno de los
dos criterios, antes que global. En caso de empate decide la prioridad
de la regla y, después, la más reciente. Así puede mantener un recargo
estándar para toda la empresa y sobrescribirlo de forma selectiva para
proveedores o grupos de mercancías concretos.

**Efecto sobre las aceptaciones de catálogo:** Las propuestas aparecen
directamente en los artículos de catálogo vinculados de los catálogos de
proveedores. Nunca llegan automáticamente al precio de venta del
artículo: en el modo directo las acepta el tramitador de forma expresa;
en el modo de cuatro ojos se genera en su lugar una solicitud de
aprobación. Una solicitud solo puede aprobarla una persona distinta del
solicitante; los rechazos pueden justificarse. El modo de aprobación
(directo o cuatro ojos) se cambia en esta página por organización, y
las solicitudes abiertas y resueltas pueden consultarse allí.

Los procesos ya concluidos y los precios históricos no se ven afectados
por los cambios de reglas — una regla modificada solo surte efecto en la
siguiente aceptación de precio. Leer requiere permisos de lectura de
almacén; administrar las reglas y las solicitudes, permisos de
configuración de almacén.
