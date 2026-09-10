---
title: "Suscripciones y licencias"
topic: finance.resale
version: 2
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

**Lista genérica:** además de las exportaciones de proveedores (Telekom,
Quality Hosting), la importación acepta cualquier lista CSV o XLSX cuyas
columnas se reconozcan por su nombre — alemán o inglés: id, empresa,
producto, cantidad, inicio, fin, intervalo, plazo, precio de compra, precio
de venta, proveedor, pedido. Obligatorios son empresa, producto e inicio.
Sin id se deriva de empresa, producto e inicio, de modo que una nueva
importación actualiza las mismas suscripciones en vez de duplicarlas. El
proveedor viene de la columna o del diálogo; una plantilla CSV está en el
diálogo de importación.

**Ceder licencias:** si dos empresas comparten sede y la segunda usa parte
de las licencias de un contrato, ceda esas licencias en el contrato («Ceder
licencias»: titular, cantidad, periodo, precio de venta). Surge una
suscripción propia para el otro titular con sus propios periodos; el
contrato planifica sus periodos con el resto. Cada titular recibe sus
propias facturas asignadas. Si un sucesor sustituye el contrato
(importación), la cesión continúa allí. Un contrato con cesiones solo se
elimina cuando las cesiones han desaparecido. Un cambio de titular en el
tiempo — una empresa se escinde y la nueva asume los contratos — también es
una cesión: todas las licencias del periodo antiguo al titular anterior; la
conciliación lo ofrece en una factura del otro cliente como «Ceder el
periodo a …», ya rellenado.

**Bandeja de entrada:** las suscripciones importadas cuya empresa el
registro aún no puede asignar a un titular llegan a la bandeja de entrada.
Por empresa decides una vez: cliente, cliente final de un socio o parque
propio — la sugerencia viene de la comparación de nombres con clientes y
clientes finales. La decisión se memoriza; la siguiente importación asigna
enseguida la misma empresa. Las filas que la importación no pudo procesar
(fecha ilegible, cantidad sin número, identificador duplicado) quedan como
hallazgos en la importación: el número en el mensaje, los detalles
desplegables en la lista.

**Periodos:** la página de periodos muestra los periodos vencidos de todas
las suscripciones con mosaicos de estado (abierto, facturado, parcial,
renunciado, en disputa). «Calcular propuestas» compara los periodos
abiertos con las posiciones de licencia de las facturas reflejadas y crea
propuestas; tú las confirmas, vinculas a mano una posición (solo facturas
del mismo destinatario, solo meses de licencia libres) o renuncias con un
motivo («cortesía»). La planificación ya no toca los periodos decididos;
«reabrir» los abre de nuevo. Si una factura vinculada se anula después en
Lexoffice, la siguiente ejecución pone el vínculo a cero meses, anota la
anulación y reabre el periodo para que pueda vincularse la factura de
sustitución.

**Borrador de factura:** de todos los periodos abiertos de un destinatario
de factura surge con un clic un borrador — con facturación Lexoffice como
borrador en Lexoffice (nada se finaliza; revisas y emites allí), con
facturación local como borrador de factura local con posiciones y vínculos
propuestos. Una posición por suscripción y periodo, cliente final en la
descripción, cantidad en meses para artículos mensuales. Los periodos
recuerdan el borrador: un segundo clic no crea un segundo, sino que nombra
el pendiente con número y fecha; solo cuando el borrador se convirtió en
factura o el periodo está decidido vuelven a estar libres. El diálogo lista
solo destinatarios con periodos abiertos y precio de venta y nombra debajo
lo que ya está en un borrador. Crear requiere el permiso *Crear borradores
de factura a partir de periodos*.

**Comprobantes de compra:** la compra real por suscripción y periodo
procede de tres fuentes: (1) facturas y abonos del proveedor en PDF
(Quality Hosting, formato alemán e inglés) — cada posición nombra contrato,
cliente final y plazo, el importe va exactamente al periodo; las posiciones
de abono sin contrato corresponden a la empresa. (2) Comprobantes recibidos
del espejo de comprobantes a prorrata: para facturas agrupadas sin
posiciones (Telekom) indicas la parte del proveedor y el mes de servicio,
el importe se reparte entre todos los periodos del mes, ponderado con su
compra prevista mensual. (3) Asientos de dominios de la gestión de
dominios, automáticamente. En la importación PDF el registro comprueba el
total: si la suma de las posiciones difiere del total del comprobante (por
ejemplo una página no leída), se importa igualmente y la diferencia se
muestra como aviso. La página de compras filtra por proveedor, fuente,
periodo y término de búsqueda; una asignación se libera siempre en bloque
por comprobante.

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

**Comprobación de precios:** por producto la compra según contrato, el
precio de catálogo y el PVP recomendado de la última lista de precios
importada frente a los precios de venta de las suscripciones (mínimo,
mediana, máximo). Avisos: «venta por debajo de compra», «venta por debajo
del PVP», «contrato más caro que el catálogo», «sin precio de venta».

**Clasificación de productos:** qué artículos de Lexoffice son productos de
suscripción lo reconoce el registro por el nombre. Por artículo puedes
forzar: «producto de suscripción» impone el reconocimiento, «nunca posición
de suscripción» mantiene fuera de propuestas, listas de facturas y
«posiciones sin suscripción» los servicios con un nombre de producto en el
texto (mantenimiento en Exchange).

**Dominios:** cada dominio de la gestión de dominios se convierte a diario
en una suscripción «Dominio» con intervalo anual desde el registro, compra
= precio de renovación y titular de la gestión de dominios mientras el
registro no haya decidido uno. El precio de venta por extensión viene del
catálogo de precios (proveedor reventa de dominios, producto p. ej. «.de»),
el artículo del artículo de Lexoffice de la extensión; los precios,
artículos y titulares mantenidos a mano sobreviven a cada ejecución. Los
dominios desaparecidos terminan en el día de referencia; si la lista de
dominios de una ejecución está vacía, no se termina nada. Las suscripciones
de dominio y sus comprobantes de compra no se crean a mano — solo llegan
por la sincronización.

**Renovaciones y suscripciones sin factura:** el informe «Renovaciones»
muestra qué suscripciones se renuevan o terminan en el periodo (mosaicos
30, 60, 90 días): la renovación es el inicio del siguiente periodo
planificado, en suscripciones canceladas cuenta el fin. «Sin factura» lista
las suscripciones cuyo periodo vencido abierto más antiguo tiene más de N
días (60 por defecto), con periodos abiertos e importe abierto. Ambos en CSV
o XLSX.

**Mosaico del panel:** el mosaico «Periodos de suscripción abiertos» (grupo
Finanzas, desactivado por defecto) muestra periodos abiertos con importe
abierto, propuestas sin confirmar y suscripciones sin titular y lleva a la
página correspondiente.

**Hallazgos de importación:** cada importación (Telekom, Quality Hosting,
lista de precios, lista genérica) registra contadores y hallazgos por fila.
Las fechas deben ser fechas (las celdas de fecha de Excel se leen; «3.2026»
o «2026» solos no), las cantidades números, los identificadores únicos
dentro de un archivo — de lo contrario la fila se omite y se indica el
motivo.

**Conservación de los archivos de importación:** los archivos de
importación subidos (pueden contener nombres de clientes finales)
permanecen 90 días en la carpeta de almacenamiento y luego los elimina la
planificación; el registro de importación con sus contadores se mantiene. A
mano: `resale:prune-imports` (--days cambia el plazo).

**Reparación de los vínculos de factura:** si el espejo de comprobantes se
reconstruyó antes con nuevos ID de posición, los vínculos confirmados
apuntan al vacío (vínculo sin texto de posición, periodo considerado sin
cubrir). El comando `lexoffice:repair-resale-links` vuelve a enganchar esos
vínculos mediante número de factura y posición de licencia; lo que no es
unívoco solo se lista (--dry-run muestra de antemano qué pasaría).
