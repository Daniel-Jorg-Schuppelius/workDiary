---
title: "Informes y precios"
topic: finance.resale.reports
version: 1
keywords:
    - informe de margen
    - margen
    - renovaciones
    - suscripciones sin factura
    - comprobación de precios
    - PVP recomendado
    - exportar propuesta de factura
audience: []
modules:
    - module.reselling
related:
    - finance.resale
    - finance.resale.periods
---

Los **informes** del registro calculan sobre los periodos vencidos: informe
de margen, propuesta de factura, renovaciones y suscripciones sin factura —
cada uno como CSV, XLSX o PDF; la página «Precios» comprueba los precios de
venta frente a compra, catálogo y PVP recomendado.

**Informe de margen:** por producto y por destinatario de factura figuran
los periodos vencidos del rango con venta prevista (cantidad × precio de
venta), facturado (importes netos de los vínculos de factura, propuestas
incluidas), compra prevista (precio del proveedor × cantidad) y compra real
de los comprobantes de compra. Margen = facturado − compra; la compra real
cuenta en cuanto cada periodo de la fila tiene una, si no la compra
prevista. Los importes nunca se suman entre monedas — con varias monedas
hay una fila por moneda y un aviso. Exportación en CSV, XLSX o PDF; la
propuesta de factura (periodos abiertos con meses de licencia abiertos e
importe) en CSV o XLSX.

**Renovaciones y suscripciones sin factura:** el informe «Renovaciones»
muestra qué suscripciones se renuevan o terminan en el periodo (mosaicos
30, 60, 90 días): la renovación es el inicio del siguiente periodo
planificado, en suscripciones canceladas cuenta el fin. «Sin factura» lista
las suscripciones cuyo periodo vencido abierto más antiguo tiene más de N
días (60 por defecto), con periodos abiertos e importe abierto. Ambos en CSV
o XLSX.

**Comprobación de precios:** por producto la compra según contrato, el
precio de catálogo y el PVP recomendado de la última lista de precios
importada frente a los precios de venta de las suscripciones (mínimo,
mediana, máximo). Avisos: «venta por debajo de compra», «venta por debajo
del PVP», «contrato más caro que el catálogo», «sin precio de venta».
Si el precio de compra de una suscripción ya no sigue el último precio del
proveedor, la página ofrece «Adoptar el precio del proveedor»: el diálogo
muestra el precio antiguo y el nuevo por suscripción; tras adoptarlo, la
importación vuelve a seguir los cambios de precio y los periodos abiertos
reciben el nuevo coste previsto.
