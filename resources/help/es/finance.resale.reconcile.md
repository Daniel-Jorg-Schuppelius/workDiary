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

**Inicio de facturación de la cuenta del portal:** si un subusuario factura
sus dominios a través del portal del proveedor, el portal solo factura desde
que existe esa subcuenta. La cuenta de licencias, el cuaderno de trabajo, el
resumen y la página de suscripción del dominio indican los años de licencia
abiertos que empezaron antes, junto con el inicio de la cuenta. «Aplicar
inicio de facturación» los decide en un solo paso: **No facturar más
(renuncia)** los cierra, con el inicio de la cuenta y su motivo. **Facturar
usted mismo** los deja abiertos y los incluye en la propuesta de factura del
registro, aunque el dominio se facture normalmente a través del portal. Los
años de licencia que empezaron después del inicio de la cuenta y los
facturados parcialmente no se tocan; después, cada periodo puede reabrirse o
darse por renunciado individualmente.

**Vínculos por revisar:** Encima del tablero aparecen los vínculos que no
pueden ser correctos: una posición cubre más meses de licencia de los que
tiene (sobrerreservada), o su periodo de prestación no coincide con el periodo
(mal ubicada). Un vínculo mal ubicado puede moverse al periodo propuesto o
quitarse; uno sobrerreservado solo puede quitarse.

**Reordenar producto:** Si los vínculos de un producto están desordenados, por
ejemplo desplazados un año, el botón en la cabecera del producto quita todos
los vínculos de factura de ese producto para este destinatario, y la propuesta
redistribuye las facturas. Los vínculos confirmados y manuales se pierden.
Renuncias, disputas y abonos se mantienen.

**Factura a otro cliente:** Si otro cliente tiene una posición libre del mismo
producto con exactamente el periodo de prestación del periodo abierto, aparece
junto al periodo, también con una sola licencia. Desde ahí puede cambiar el
titular o ceder el periodo.

**Posición sobrante:** Si todos los periodos de un producto están cubiertos y
una factura queda libre, aparece en el tablero como «facturado dos veces» o «facturado tras el fin». O se facturó
dos veces (abono), o falta la suscripción en el registro (crear suscripción),
o la posición no corresponde aquí (ignorar). Si crea la suscripción desde la
posición y poco antes terminó una suscripción del mismo producto, el diálogo
la ofrece como predecesor.

**De más, distinto, falta — en euros:** la lista de conciliación y el resumen
muestran por destinatario lo que se facturó de más (línea en un año de
licencia ya cubierto: «facturado dos veces»; línea tras el último año de
licencia del producto: «facturado tras el fin») y lo que se facturó distinto.
Lo que falta aparece como importe abierto. Las líneas «fuera del plazo» o «sin
suscripción» no se cuentan: normalmente falta una suscripción en el registro.

**Vínculo divergente:** si asigna una línea cuyo artículo no es el de la
suscripción o cuyo importe se desvía más de un 5 % (al menos 1 €) del
previsto, el diálogo pide una justificación. El vínculo sigue siendo posible;
la justificación queda en el periodo. Los vínculos existentes sin
justificación aparecen entre las incidencias como «Otro artículo» o «Importe
distinto» y pueden justificarse o quitarse allí.

**Cuadro de licencias y hoja de corrección:** el espacio de trabajo muestra
arriba producto × año de licencia en todos los proveedores del cliente. La
hoja de corrección debajo indica las facturas que hay que abonar, los años de
licencia que hay que facturar, las desviaciones y el saldo; se descarga como
XLSX. Un abono que hace referencia a la factura retira sus líneas de la lista.
