---
title: "Conciliación de licencias"
topic: finance.reselling
version: 1
audience: []
modules:
    - module.finance
related:
    - finance.transfers
    - roles.buchhaltung
    - glossary.core
---

La **conciliación de reventa de licencias** comprueba que cada periodo de
facturación de las suscripciones Microsoft 365 revendidas esté cubierto por
una factura emitida en Lexoffice y compara los precios de venta con los de
compra.

**Lo que subes:** la exportación del Telekom Cloud Marketplace
(purchases.csv), la exportación de contratos del portal de socios de
Quality Hosting (XLSX) y, opcionalmente, su lista de precios. Ambas
exportaciones forman el parque antes y después de la migración; las
sucesiones se detectan y la duración Telekom se corta al inicio del contrato
de Quality Hosting.

**Lo que hace la ejecución:** divide cada suscripción en periodos anuales o
mensuales, asigna cada empresa del marketplace a un contacto de Lexoffice
(archivo de asignación, número de cliente del socio, maestro de clientes,
búsqueda inequívoca por nombre — nunca adivinando) y busca para cada periodo
una línea de factura coincidente en la ventana alrededor del inicio del
periodo.

**Estado por periodo:** Cubierto, Por debajo del coste, Parcial, Faltante, Sin asignación. Las
empresas sin asignación se resuelven en la próxima ejecución con un archivo
de asignación: una línea por empresa, `Empresa;UUID del contacto Lexoffice`
o `Empresa;customer:<Sqid>`.

**Comprobación de precios:** por producto ves el precio de compra de los
contratos, el precio de lista actual y el PVP recomendado del fabricante,
además de los precios de venta unitarios realmente facturados. Aparece un
aviso si tu precio está por debajo del coste o del PVP recomendado, o si un
contrato activo es más caro que la lista actual.

La ejecución lee Lexoffice en segundo plano y tarda algunos minutos con
muchos clientes. No escribe nada en Lexoffice ni en los datos maestros — el
informe vive solo en la ejecución y se descarga como CSV.
