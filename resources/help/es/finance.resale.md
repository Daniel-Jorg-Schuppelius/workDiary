---
title: "Suscripciones y licencias"
topic: finance.resale
version: 1
audience: []
modules:
    - module.reselling
related:
    - roles.buchhaltung
    - glossary.core
---

El **registro de reventa** lleva cada servicio recurrente revendido como
suscripción: licencias de Microsoft 365, dominios, hosting, buzones, copias de
seguridad u otros — independiente del proveedor, en una sola lista.

**Titular:** cada suscripción tiene exactamente un titular. Un **cliente** se
factura directamente. Un **cliente final** pertenece a un socio; la factura va
al socio, que la traslada. El **parque propio** (licencias internas, dominios
propios) nunca se factura. Las suscripciones sin titular esperan asignación y
cuentan en «Sin titular».

**Plazo y periodos:** a partir del inicio, el intervalo de facturación (anual
o mensual) y el fin, el registro planifica los periodos de facturación
esperados — hasta 90 días por adelantado para ver la próxima renovación. Una
suscripción sin fin se renueva automáticamente. Un resto al final del plazo
más corto que un mes (anual) o cinco días (mensual) es un resto de alineación,
no un periodo. Los periodos decididos (facturado, parcial, renunciado, en
disputa) sobreviven a cualquier replanificación; los abiertos siguen los
cambios de cantidad, precio y fin.

**Precios:** compra y venta por unidad e intervalo, netos. El artículo aporta
producto y precio de venta para las facturas. Venta prevista por periodo =
cantidad × precio de venta.

**Estado:** activa, cancelada (fin conocido, se planifican periodos hasta
entonces), sustituida (sucesor en otro proveedor) y terminada. Las
suscripciones terminadas y sustituidas no reciben nuevos periodos.

**Eliminar:** una suscripción con periodos decididos no se puede eliminar —
ponla en «terminada». Permisos: ver con *Ver el registro de reventa*,
gestionar con *Gestionar el registro de reventa*.

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
