---
title: "Matriz de reglas fiscales"
topic: finance.tax-rules
version: 1
audience:
    - admin
related:
    - invoices.manage
---

La matriz de reglas fiscales es el catálogo versionado del que la
facturación local obtiene sus tipos impositivos. WorkDiary suministra un
catálogo base; las filas propias de la organización lo sobrescriben —
el catálogo suministrado en sí permanece inalterado.

**Estructura:** Cada regla se aplica a un país (opcionalmente una
región), una categoría (services, goods, shipping, materials, expenses,
construction, media, other) y un tipo de tasa (standard, reduced, zero,
exempt, reverse_charge, export) — con porcentaje, válido desde/hasta,
indicación de fuente y nota.

**Lógica de fecha de referencia:** Lo determinante es la fecha de la
prestación, no la fecha de la factura. Se aplica la regla activa más
reciente vigente en la fecha de referencia; las filas de la organización
tienen prioridad sobre el catálogo. Si no existe nada específico para
una categoría, se aplica la categoría de servicios como respaldo.

**Advertencias:** Al crear y al importar, la comprobación de
solapamientos impide que dos reglas activas del mismo ámbito de
aplicación se superpongan temporalmente. La vista general advierte
además de lagunas en cadenas de reglas activas — períodos para los que
no rige ninguna regla.

**Importación CSV:** Archivo separado por punto y coma con las columnas
country, category, rate_type, rate, valid_from, valid_to, source, note
(fila de cabecera permitida). Las filas con categoría/tipo de tasa
desconocidos o con solapamiento se notifican y se omiten; el resto se
importa.

**Desactivar en lugar de borrar:** Las reglas nunca se borran, sino que
se desactivan — después vuelven a regir el catálogo o las reglas más
antiguas. La creación y la desactivación quedan auditadas; solo pueden
desactivarse las filas propias de la organización.

**Congelación al emitir:** Al emitir una factura, el contexto fiscal
realmente utilizado (tasa, fuente de la regla, fecha de referencia,
categoría, desglose de impuestos) se congela en el documento. Los
cambios de reglas posteriores solo afectan así a documentos nuevos,
nunca a los ya emitidos.

**Casos especiales con prioridad:** La configuración de pequeño
empresario (§ 19 UStG) desactiva por completo el desglose de impuestos.
Un tipo impositivo estándar fijado por la organización prevalece sobre
la matriz en el ámbito nacional. Los clientes de la UE con NIF-IVA
formalmente válido reciben automáticamente inversión del sujeto pasivo
(reverse charge, 0 %), y los clientes de terceros países la indicación
de exportación (0 %) — las filas correspondientes de la matriz aportan
para ello el texto indicativo en el documento.
