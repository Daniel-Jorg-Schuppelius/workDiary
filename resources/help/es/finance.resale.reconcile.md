---
title: "Conciliación por destinatario"
topic: finance.resale.reconcile
version: 1
keywords:
    - conciliación
    - destinatario de factura
    - lista de trabajo
    - importe abierto
    - nunca facturado
    - factura a otro cliente
audience: []
modules:
    - module.reselling
related:
    - finance.resale
    - finance.resale.board
    - finance.resale.periods
---

La **conciliación** contrapone, por destinatario de factura, los periodos
vencidos a las líneas de licencia de sus facturas. La lista indica por
destinatario el importe abierto, los hallazgos de plantillas y si está
terminado; el filtro predeterminado oculta los destinatarios terminados, y
«Procesar siguiente destinatario» abre la primera carpeta de trabajo abierta.

**Conciliación por destinatario de factura:** cuando quedan periodos
abiertos y no está claro si falta una factura o solo la asignación, use la
conciliación (botón en la lista de suscripciones, en la página de periodos
y en el cliente). Por destinatario — el cliente con sus clientes finales —
contrasta los periodos vencidos de todas las suscripciones con las líneas
de licencia de sus facturas, en meses de licencia por producto: *previsto*
según los periodos, *facturado* según las líneas. El hallazgo dice qué
hacer: «solo sin asignar» (las líneas libres bastan — asignar), «nunca
facturado» (más periodos que líneas — factura de regularización mediante el
borrador o renuncia al periodo) o «sin periodo» (más líneas que periodos —
falta una suscripción en el registro o doble facturación). Por cada periodo
abierto se listan las líneas del mismo producto con su distancia al inicio
del periodo: las libres con asignación, las consumidas con su titular para
control. La fecha de referencia es el **periodo de prestación** de la
factura, si no la fecha de factura; licencias y meses se muestran por
separado («5 × 12 meses» = cinco licencias por un año). Además tres trampas
invisibles por suscripción: **factura a otro cliente** (la cuenta del
proveedor no es el cliente, o el cliente final se factura directamente en
vez de a través del socio; reconocido por una parte común del nombre) — la
solución es «Titular → cliente»: la suscripción pasa a ese cliente y la
propuesta se aplica de inmediato. Las facturas **anuladas** cerca del
inicio del periodo explican un periodo vacío. Las suscripciones de la
**bandeja de entrada** cuya empresa aparece en los textos de factura
esperan su titular. Una línea libre que ya no toca ningún periodo de su
producto señala un contrato ausente en el registro (la exportación del
proveedor no lo conoce): la fila del producto dice «Línea sin suscripción
desde …» y «Crear suscripción desde la línea» abre el diálogo con artículo,
cantidad, inicio y precio tomados de la factura. La asignación puede
dirigirse a un periodo de otra suscripción del mismo destinatario — nunca a
un periodo de otro cliente.
