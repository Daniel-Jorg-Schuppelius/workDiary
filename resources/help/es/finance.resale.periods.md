---
title: "Periodos de facturación"
topic: finance.resale.periods
version: 1
keywords:
    - periodos
    - calcular propuestas
    - borrador de factura
    - acciones masivas
    - renuncia
    - disputado
    - facturación retroactiva
    - pago anticipado
    - fecha de corte
    - ejecución periódica
audience: []
modules:
    - module.reselling
related:
    - finance.resale
    - finance.resale.plan
    - finance.resale.board
---

La **página de periodos** lista los periodos de facturación vencidos de todas
las suscripciones: aquí decide por periodo (confirmar, asignar, renunciar,
disputar), inicia el cálculo de propuestas, crea borradores de factura y abre
la propuesta de plantillas. Los mosaicos de estado y el filtro «Solo
problemas» acotan la lista.

**Periodos:** la página de periodos muestra los periodos vencidos de todas las
suscripciones con mosaicos de estado (abierto, facturado, parcial, renunciado,
en disputa). «Calcular propuestas» compara los periodos abiertos con las
posiciones de licencia de las facturas reflejadas y crea propuestas; usted las
confirma, vincula a mano una posición (solo facturas del mismo destinatario,
solo meses de licencia libres) o renuncia con un motivo («cortesía»).
Confirmar requiere una propuesta abierta; renunciar o disputar, un periodo
vencido aún sin decidir: la acción individual y la masiva comprueban lo mismo.
En el diálogo de vinculación aparecen primero las facturas dentro de la
ventana del periodo y debajo todas las demás del destinatario; el campo de
búsqueda encuentra cualquiera por su número, y una ya usada indica dónde está
vinculada. Las listas de suscripciones y de periodos también buscan por número
de factura. La
planificación ya no toca los periodos decididos; «reabrir» los abre de nuevo.
Si una factura vinculada se anula después en Lexoffice, la siguiente ejecución
pone el vínculo a cero meses, anota la anulación y reabre el periodo para que
pueda vincularse la factura de sustitución. Las suscripciones de dominio se
cubren con posiciones que nombran el dominio (por ejemplo «dominio .de incl.
gestión (lastkraft-logistik.de)»), independientemente de la clasificación del
artículo. Los dominios que un subrevendedor liquida a través del portal del
proveedor se cubren con el diario contable del módulo de dominios: un
comprobante por subusuario y mes, numerado «número de cliente-MM-AAAA»
(introduzca el número de cliente en la página del subusuario); el destinatario
es el cliente del subusuario.

**Propuestas seguras e inseguras:** La ejecución de propuestas (cada hora y
tras cada importación) confirma por sí misma las coincidencias seguras — un
comprobante cuya fecha de factura está como máximo 14 días antes y 45 días
después del inicio del periodo y que cubre el periodo por completo con el
número de licencias correcto; la confirmación figura en el registro como
«confirmada automáticamente». Todo lo demás sigue siendo propuesta y lleva
«insegura» con el motivo en la ayuda emergente. Las propuestas valen sin
confirmación: la página de periodos muestra por defecto «Solo problemas»
(abierto, parcial, en disputa); las propuestas sin confirmar se consultan
como muestreo mediante el filtro y el estado del resumen. Un clic en el número
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

**Borrador de factura:** de todos los periodos abiertos de un destinatario
de factura surge con un clic un borrador — con facturación Lexoffice como
borrador en Lexoffice (nada se finaliza; revisa y emite allí), con
facturación local como borrador de factura local con posiciones y vínculos
propuestos. Una posición por suscripción y periodo, cliente final en la
descripción, cantidad en meses para artículos mensuales. Los periodos
recuerdan el borrador: un segundo clic no crea un segundo, sino que nombra
el pendiente con número y fecha; solo cuando el borrador se convirtió en
factura o el periodo está decidido vuelven a estar libres; hasta entonces muestran «En borrador». El diálogo lista
solo destinatarios con periodos abiertos y precio de venta y nombra debajo
lo que ya está en un borrador. Crear requiere el permiso *Crear borradores
de factura a partir de periodos*.
Dos clics simultáneos (doble clic, segunda ventana del navegador) también
crean un solo borrador; el segundo avisa de que ahora mismo se está creando
un borrador.

**Facturación retroactiva y planificación anticipada:** La propuesta de
factura agrupa los periodos abiertos consecutivos de una suscripción en una
línea — siempre en artículos mensuales, en suscripciones anuales con una
licencia («3 año» para tres años abiertos); los periodos parcialmente
cubiertos y los huecos abren una línea nueva. Así los años antiguos se
facturan en un solo borrador. Para pagos anticipados el diálogo tiene una
fecha de corte (solo vencidos, +1, +2, +3 años): la planificación crea los
periodos hasta esa fecha, y la ejecución diaria conserva los periodos con
sello de borrador. Con soberanía de facturación externa, la ejecución
periódica puede crear cada mes, el día uno, un traspaso por destinatario a su
programa de contabilidad (interruptor y antelación en el diálogo
«Facturación»); las suscripciones con plantilla periódica activa quedan
fuera.
