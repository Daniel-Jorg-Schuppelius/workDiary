---
title: "Presupuestos de obra GAEB"
topic: boq.overview
version: 1
audience: []
related:
    - projects.manage
    - invoices.manage
---

Los presupuestos de obra (Leistungsverzeichnisse, LV) representan de
forma estructurada las prestaciones de construcción — desde el
intercambio de datos GAEB importado, pasando por la medición y el
cálculo, hasta la exportación del estado actual.

**Importación con preflight:** Se leen archivos GAEB-DA-XML de la
versión 3.x en las fases de intercambio X81 a X86 (presupuesto de obra,
estimación de costes, solicitud de oferta, presentación de oferta,
oferta alternativa, adjudicación del encargo). Antes de escribir, un
preflight comprueba la versión, la fase de intercambio, la estructura,
la unicidad de los números de orden y la plausibilidad de cantidades y
unidades. Los hallazgos bloqueantes generan únicamente un protocolo de
importación — no se escribe nada. Una reimportación a un LV existente se
cancela si fuera a sobrescribir partidas con referencias de ejecución o
de facturación.

**Estructura y estados de precios:** Un LV consta de una cabecera,
secciones jerárquicas con números de orden y partidas con texto corto y
largo, cantidad, unidad y precio unitario. Cada importación deposita
snapshots de precios, de modo que los estados de precios anteriores
siguen siendo trazables. Un LV puede asignarse a un proyecto; las
partidas pueden vincularse con artículos o material.

**Medición y cálculo posterior:** Los avances se registran de forma
aditiva por partida (cantidad, fuente, nota). Las partidas con una
primera medición pasan automáticamente a «en ejecución». El cálculo
posterior contrapone lo previsto (cantidad prevista × precio unitario),
lo real (cantidad medida × precio unitario), la prestación restante y el
grado de avance — es un análisis y no sustituye la facturación.

**Flujo de trabajo:** El LV y las partidas individuales recorren
transiciones de estado dirigidas, desde la licitación pasando por la
oferta y el encargo hasta la ejecución y el cierre; los saltos no
válidos se rechazan. Los adicionales se gestionan como partidas propias,
y la vista de prestación restante muestra lo que sigue pendiente.

**Exportación:** El estado actual del LV puede descargarse como
GAEB-DA-XML en una fase de intercambio seleccionable (estándar:
adjudicación del encargo). La exportación es determinista y se registra
con un hash de contenido — el mismo estado produce de forma reproducible
el mismo hash.
