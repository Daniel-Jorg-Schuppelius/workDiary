---
title: "Suscripciones y licencias"
topic: finance.resale
version: 7
keywords:
    - Vista previa de importación
    - Acciones masivas
    - facturación de suscripciones
    - servicios recurrentes
    - reventa de servicios
    - periodos de facturación
    - renovaciones
    - plazo de preaviso
    - margen
    - factura recurrente
    - importar suscripciones
    - revendedor
    - Lexoffice
    - Telekom
    - Quality Hosting
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
En la lista de suscripciones, filtre «Solo sin titular», seleccione varias
suscripciones y asígnelas con «Asignar titular» en un paso a un cliente, un
cliente final o al parque propio; una sola suscripción va por el icono de su
fila. Los dominios reciben el titular también en la gestión de dominios, y la
bandeja lleva allí las suscripciones sin nombre de empresa.

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
póngala en «terminada». Permisos: ver con *Ver el registro de reventa*,
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
Por empresa decide una vez: cliente, cliente final de un socio o parque
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
propuestas; usted las confirma, vincula a mano una posición (solo facturas
del mismo destinatario, solo meses de licencia libres) o renuncia con un
motivo («cortesía»). La planificación ya no toca los periodos decididos;
«reabrir» los abre de nuevo. Si una factura vinculada se anula después en
Lexoffice, la siguiente ejecución pone el vínculo a cero meses, anota la
anulación y reabre el periodo para que pueda vincularse la factura de
sustitución.

**Borrador de factura:** de todos los periodos abiertos de un destinatario
de factura surge con un clic un borrador — con facturación Lexoffice como
borrador en Lexoffice (nada se finaliza; revisa y emite allí), con
facturación local como borrador de factura local con posiciones y vínculos
propuestos. Una posición por suscripción y periodo, cliente final en la
descripción, cantidad en meses para artículos mensuales. Los periodos
recuerdan el borrador: un segundo clic no crea un segundo, sino que nombra
el pendiente con número y fecha; solo cuando el borrador se convirtió en
factura o el periodo está decidido vuelven a estar libres. El diálogo lista
solo destinatarios con periodos abiertos y precio de venta y nombra debajo
lo que ya está en un borrador. Crear requiere el permiso *Crear borradores
de factura a partir de periodos*.

**Facturación recurrente:** con la facturación local el registro puede
crear los borradores por sí mismo cada día. El interruptor está en la
página *Clasificación de productos* (permiso *Gestionar el registro de reventa*):
activo = la próxima ejecución crea, por cada destinatario con periodos
vencidos aún sin borrador, el mismo borrador que el clic — posiciones por
suscripción y periodo, periodos vinculados como propuesta y marcados. La
*antelación* en días incluye también periodos antes de su inicio (0 = solo
periodos vencidos). Los destinatarios facturados vía Lexoffice/DATEV no se
tocan, los periodos sin precio de venta se omiten, el inventario propio
nunca se factura. Los borradores se finalizan en la lista de facturas; el
resumen semanal indica cuántos borradores de los últimos siete días siguen
abiertos. Una vez o de prueba: `php artisan resale:draft-local
--organization=… --dry-run` (`--force` ignora el interruptor).

**Comprobantes de compra:** la compra real por suscripción y periodo
procede de tres fuentes: (1) facturas y abonos del proveedor en PDF
(Quality Hosting, formato alemán e inglés) — cada posición nombra contrato,
cliente final y plazo, el importe va exactamente al periodo; las posiciones
de abono sin contrato corresponden a la empresa. (2) Comprobantes recibidos
del espejo de comprobantes a prorrata: para facturas agrupadas sin
posiciones (Telekom) indica la parte del proveedor y el mes de servicio,
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

**Clasificación de productos:** la lista muestra todos los artículos del
catálogo de artículos — los artículos activos del maestro de artículos y los
de programas de contabilidad conectados como Lexoffice, con su origen.
Cuáles son productos de suscripción lo reconoce el registro por el nombre.
Por artículo puede forzar: «producto de suscripción» impone el
reconocimiento, «nunca posición de suscripción» mantiene fuera de
propuestas, listas de facturas y «posiciones sin suscripción» los servicios
con un nombre de producto en el texto (mantenimiento en Exchange). La
clasificación actúa igual para cada origen: decide qué posiciones de factura
trata el espejo como posiciones de licencia, y la comprobación de precios
compara el precio de venta del artículo con los precios de las suscripciones
(un precio mensual cuenta doce veces en una suscripción anual).

**Contratos:** una suscripción puede llevar un contrato de la gestión de
contratos como marco de plazos (campo «Contrato» en el diálogo de la
suscripción; solo contratos de cliente cuya contraparte es el destinatario
de la factura de la suscripción). El expediente del contrato muestra sus
suscripciones en el panel «Suscripciones y licencias». Sin segundo catálogo
de plazos: la ejecución diaria anota una fecha por suscripción en el
calendario contractual — el plazo de cancelación al final para
suscripciones canceladas, un aviso de renovación antes del siguiente
periodo para la renovación automática, cada una adelantada por el plazo de
preaviso del contrato (30 días si no está indicado), con 14 días de
antelación. Si cambia el final, la fecha se mueve con él; las fechas
completadas siguen completadas; las suscripciones finalizadas o sin
contrato cierran su fecha abierta.

**Dominios:** cada dominio de la gestión de dominios se convierte a diario
en una suscripción «Dominio» con intervalo anual desde el registro, compra
= precio de renovación y titular de la gestión de dominios mientras el
registro no haya decidido uno. El precio de venta por extensión viene del
catálogo de precios (proveedor reventa de dominios, producto p. ej. «.de»),
el artículo del catálogo de artículos (maestro de artículos o programa de contabilidad) para la extensión; los precios,
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

**Cuenta de licencias:** Una cuenta lleva el nombre de su titular; si la
factura va a un socio, este se muestra al lado («vía LDS Systems»), y las
plantillas periódicas del socio aparecen solo en la cuenta del cliente final
al que están asignadas. La pestaña *Cuentas de licencias* muestra por cuenta
de proveedor (número de cliente en el proveedor, incluidos los predecesores
de un cambio de proveedor) cuántas licencias por producto están activas,
quién las tiene y qué año de licencia está facturado, como cuadrícula
producto × titular × año con número de factura o «falta». Si varias empresas
tienen licencias de la misma cuenta, mantenga el **reparto** por producto
(«Märkische Bunker 5 × Exchange desde 18.10.2025»): los demás titulares
llenan primero el contrato con mayor cantidad, luego por inicio; el titular
del contrato se queda con el resto. Las cesiones por contrato se derivan de
ahí, siguen cada cambio y pasan al sucesor al cambiar de proveedor; las
cesiones manuales siguen siendo posibles como excepción. La planificación
también recalcula contratos sustituidos y finalizados de los últimos tres
años para que un reparto posterior aún cambie su objetivo. Las
**incidencias en vínculos** muestran posiciones que cubren más meses de
licencia de los que tienen (sobreasignadas) y vínculos cuyo periodo de
servicio no coincide con el periodo, con «Mover a …» al periodo adecuado de
la misma suscripción o «Desvincular»; el diálogo de vínculo y la asignación
rápida avisan antes con ⚠.

**Plantillas periódicas (plan):** Si un programa de contabilidad emite las
facturas, las facturas recurrentes se crean allí como plantillas periódicas. El
registro las refleja (Lexoffice: a diario y tras la sincronización de
comprobantes, solo lectura) y muestra en la cuenta de licencias por plantilla su
estado, ritmo, próxima ejecución y las posiciones de licencia con las
suscripciones que facturan. La asignación suscripción ↔ posición la propone la
ejecución — primero por la factura que la plantilla creó por última vez y que
está asignada a un periodo, si no por mismo destinatario, artículo y cantidad
(con varios clientes finales de un socio solo si se nombran): usted confirma, asigna a mano o quita. Los periodos de una
suscripción con plantilla activa quedan fuera de la propuesta de factura. Las
**comprobaciones existencias ↔ plan** avisan antes de la factura: suscripción
sin plantilla, plantilla sin suscripción («Crear suscripción desde la plantilla»
toma artículo, cantidad, ritmo y precio), cantidad distinta, plantilla que sigue
tras el fin de la suscripción, plantilla pausada o finalizada con suscripción
activa, próxima ejecución a más de siete días del inicio del periodo, última
ejecución fallida: en el mosaico, el resumen semanal y la cola de trabajo. Cada
factura reflejada lleva la referencia a su plantilla.

**Resumen y pestañas:** La entrada «Suscripciones y licencias» ya no muestra
la lista de suscripciones sino una **lista de tareas**: periodos vencidos
sin factura (con importe abierto), propuestas sin confirmar, incidencias en
vínculos de factura, incidencias en plantillas recurrentes, repartos por
encima del inventario, suscripciones sin titular, renovaciones o finales en
30 días y exportaciones de proveedor desactualizadas — cada fila con número
y salto a la pantalla donde se decide. Al lado, el **estado**: la última
ejecución de propuestas (con «Calcular propuestas») y por proveedor la
última importación con su antigüedad. Las pestañas siguen el flujo: Resumen
· Cuentas de licencia · Conciliación · Periodos · Bandeja │ Suscripciones ·
Inventario de licencias · Compras │ Informes · Precios; la clasificación de
productos y la facturación están en el menú ⋯. La lista de suscripciones se
abre con «Suscripciones».

**Propuestas seguras e inseguras:** La ejecución de propuestas (cada hora y
tras cada importación) confirma por sí misma las coincidencias seguras — un
comprobante cuya fecha de factura está como máximo 14 días antes y 45 días
después del inicio del periodo y que cubre el periodo por completo con el
número de licencias correcto; la confirmación figura en el registro como
«confirmada automáticamente». Todo lo demás sigue siendo propuesta y lleva
«insegura» con el motivo en la ayuda emergente. Por eso la página de
periodos muestra por defecto «Propuestas sin confirmar»; el filtro «Solo
problemas» y los mosaicos de estado se mantienen. Un clic en el número
de comprobante abre el comprobante: posiciones, periodo de prestación, texto
de la factura, consumo por posición y la comparación con el periodo (fecha
dentro de la ventana, prestación, artículo); así ve antes de confirmar si la
factura pertenece al periodo.

**Acciones masivas:** En la página de periodos seleccione filas con las
casillas y confirme todas las propuestas de una vez, renuncie con un motivo
para todas («Renunciar / disputar …») o cree un borrador por destinatario de
factura de la selección. Los periodos decididos y futuros se omiten y se
cuentan; cada periodo recibe la misma entrada de registro que en la acción
individual. No hay preselección.

**Clasificación de productos con filtro:** La clasificación muestra por
defecto solo productos de suscripción y artículos con suscripciones o líneas
de plantilla; «Todos los artículos», «Sin clasificación» y la búsqueda por
nombre o número acotan. Si un programa de contabilidad emite las facturas,
cada artículo muestra en cuántas plantillas recurrentes activas aparece.

**Importación con vista previa:** Tras subir los archivos, el registro
muestra primero lo que cambiaría la importación — por archivo nuevo,
modificado (cantidad, precio, estado, inicio, fin con el valor anterior),
sin cambios y las suscripciones planificables del proveedor que el archivo
ya no incluye (la importación no las finaliza). Se aplica solo tras
«Aplicar»; «Descartar» elimina los archivos. Si el proveedor cambia un
precio, la importación actualiza el precio de compra de la suscripción
mientras coincida con el último precio del proveedor — un precio introducido
a mano se mantiene — y escribe una línea de catálogo con fecha del archivo
(fecha en el nombre como «2026-10-26_Export.xlsx», si no el día de la
importación). Si la última importación de un proveedor tiene más de 30
días, el resumen lo indica y el resumen semanal lo recuerda.

**Compras desde la bandeja de facturas:** En la pestaña Compras, con
«Asignar proveedores» vincule un suministrador del maestro al proveedor (el
nombre ofrece una sugerencia; nunca se asigna automáticamente). En cuanto
una factura recibida de ese suministrador queda asignada en la bandeja, el
registro la incorpora como compra: Quality Hosting por posición mediante el
lector de facturas (contrato y duración por posición), proveedores de
dominios por nombre de dominio sobre la suscripción del dominio (solo si el
diario contable del registrador no aporta ya los costes), el resto a
prorrata sobre los periodos del mes de factura. Cada línea lleva la factura
recibida como origen; una segunda ejecución la reconoce por su hash. Las
posiciones sin suscripción quedan como «sin suscripción» — el filtro «Solo
sin suscripción» las muestra, «Asignar suscripción» las vincula a la
suscripción correcta y al periodo de la fecha del documento.
`resale:import-incoming --since=` recupera facturas asignadas con carácter
retroactivo. La cuenta de licencia muestra por producto la compra prevista y
real y el margen de los últimos doce meses; el resumen semanal informa de
suscripciones cuyo precio contractual difiere del último precio de catálogo
o de proveedor importado.

**Vínculo de factura en licencias por unidad:** Al vender una licencia
del inventario elige la línea de factura del programa de contabilidad que
factura esa licencia; la lista muestra las líneas del cliente elegido en
torno a la fecha de venta, las coincidentes (mismo artículo o nombre de
producto) van primero y se proponen. Sin línea, la referencia de factura
sigue siendo texto libre. La lista de licencias, la página del lote y la
ficha del cliente muestran para la línea vinculada el número de comprobante
con salto al programa de contabilidad y a la imagen del comprobante. Una
venta que tras 30 días no lleva ni línea ni referencia aparece como caso de
comprobación «Licencias por unidad sin factura» en el resumen, en la
conciliación del destinatario y mediante el filtro «Sin factura» del
inventario de licencias; añada el vínculo con «Corregir venta». En el portal
de clientes, los clientes ven sus licencias vendidas con el número de
factura, nunca la clave.
