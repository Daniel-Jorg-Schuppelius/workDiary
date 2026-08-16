---
title: "Catálogos de proveedores"
topic: supplier-catalogs.overview
version: 1
audience: []
related:
    - articles.master
    - procurement.orders
---

Los catálogos de proveedores mantienen en el sistema las listas de
precios de sus proveedores — separadas del maestro de artículos propio,
pero vinculables con él.

**Fuentes de catálogo:** Por proveedor se crean una o varias fuentes.
Los formatos admitidos son DATANORM, BMEcat y CSV con mapeo de columnas
de libre asignación (número de artículo, denominación, precio de compra,
moneda, GTIN, número de fabricante, grupo de mercancías, disponibilidad,
plazo de entrega). Los archivos llegan por carga manual o por
recuperación remota automática en un intervalo seleccionable; una
shopinfo.xml cargada rellena previamente el mapeo, el juego de
caracteres y el separador. El mapeo se guarda en la fuente y se
reutiliza en recuperaciones posteriores.

**DATANORM en detalle:** Se admiten las versiones 4 y 5 — además de los
archivos de artículos (DATANORM.nnn), también grupos de descuento
(DATANORM.RAB), grupos de mercancías (DATANORM.WRG) y archivos de
precios (DATPREIS.nnn). Los precios de lista (indicador 1) se convierten
en precios netos de compra mediante el grupo de descuento; los archivos
de cambios no tocan las existencias (modo de procesamiento seleccionable
en el diálogo de importación). En archivos de precios específicos del
cliente, el registro de control K se comprueba contra el número de
cliente guardado en la fuente. El juego de caracteres suele ser CP850.
En sentido inverso, la lista de artículos exporta el propio maestro como
catálogo DATANORM o archivo de precios DATPREIS (también por acceso de
catálogo B2B con precios del cliente).

**Importación:** Cada ejecución resume cuántos artículos de catálogo se
crearon, se actualizaron, cambiaron de precio o se marcaron como
descatalogados. Los artículos de catálogo llevan, además del precio de
compra, precios escalonados.

**Vinculación (fuentes de suministro):** Los artículos de catálogo se
vinculan manualmente o mediante propuesta por GTIN/EAN con artículos
propios (también variantes). Solo esta vinculación establece la fuente
de suministro — el maestro de artículos en sí no se ve afectado por la
importación. Las vinculaciones pueden deshacerse en cualquier momento.

**Cotejo de precios con aprobación:** Si una importación modifica el
precio de compra de un artículo vinculado, se genera una advertencia de
cálculo que debe revisarse y confirmarse. A partir de las reglas de
margen, el sistema calcula propuestas de precio de venta directamente en
el artículo de catálogo. La aceptación en el artículo nunca es
automática: en el modo directo la realiza el tramitador de forma
expresa; en el modo de cuatro ojos se genera en su lugar una solicitud
de aprobación que una segunda persona debe aprobar o rechazar.

**OCI-Punchout:** Las fuentes con acceso de tienda registrado permiten
el salto directo a la tienda web del proveedor. El carrito llenado allí
regresa mediante un retorno firmado y limitado en el tiempo, y se asigna
al almacén de destino elegido — como base para la adquisición
posterior.

La lectura es posible con permisos de lectura de almacén; crear,
importar y vincular requieren permisos de contabilización de almacén.
