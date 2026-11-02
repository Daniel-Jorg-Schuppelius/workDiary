---
title: "Carpeta de trabajo y tablero de conciliación"
topic: finance.resale.board
version: 1
keywords:
    - carpeta de trabajo
    - tablero de conciliación
    - línea de tiempo
    - encaje
    - traspaso
    - impago
    - insolvencia
    - cambio de pedido
audience: []
modules:
    - module.reselling
related:
    - finance.resale
    - finance.resale.reconcile
    - finance.resale.subscription
---

La **carpeta de trabajo** de un destinatario de factura es el lugar donde se
asigna: arriba sus suscripciones, debajo, por producto, el tablero de
conciliación con periodos y líneas de factura en una línea de tiempo. Los
mosaicos sobre el tablero lo filtran a los productos afectados.

**Carpeta de trabajo y tablero de conciliación:** la conciliación de un
destinatario es la carpeta de trabajo: arriba las suscripciones con editar,
traspaso, ceder licencias y devolución, debajo el tablero de conciliación
por producto. A la izquierda todos los periodos por fecha con previsto y
cobertura, a la derecha las líneas de factura: las asignadas con su periodo
de servicio, para periodos abiertos las libres que encajan, facturas a otros
clientes y anulaciones. Las líneas sin periodo adecuado aparecen en una fila
propia «sin periodo» en su fecha. Cada factura libre aparece solo en el
periodo que mejor le encaja, y «Asignar …» la asigna con un clic; la
cantidad es lo que el periodo aún necesita. Un desfase de algunas semanas
entre la fecha de pedido en el proveedor y la fecha de factura es normal y
no se muestra; una factura agrupada que cubre todo el periodo encaja. Las
facturas sin periodo adecuado aparecen en una fila «sin periodo» con «Crear
suscripción» o «Asignar a otro periodo». Las asignaciones dudosas piden
confirmación antes de guardar; en el diálogo de vínculo y en la asignación
rápida de la página de la suscripción usted las confirma con la casilla. Los
vínculos que no encajan en el tiempo aparecen en amarillo: revíselos y
deshágalos si es necesario. «Trabajar el siguiente destinatario» en la vista
general recorre todos los destinatarios con importe abierto; un destinatario
está terminado cuando no queda ningún periodo vencido abierto ni ningún
hallazgo de plantilla. Si el proveedor renueva una suscripción como pedido
nuevo, la ejecución de propuestas renuncia al breve periodo residual del
pedido anterior y traslada sus vínculos al primer periodo del nuevo. El periodo
residual aparece entonces como «Cambio de pedido», no como renuncia: las
facturas del sucesor cubren ese tiempo.

**Encaje:** Cada línea de factura lleva un encaje con el periodo — según la
distancia entre el inicio del periodo y el inicio de ciclo más cercano de su
periodo de prestación, o con la fecha de factura si no hay periodo de
prestación. Hasta 14 días cuenta como exacto, hasta 90 días como desplazado;
las líneas que solo se solapan, están muy lejos o fuera, o encajan claramente
mejor en otro periodo abierto del mismo producto preguntan antes de asignarse:
en la carpeta como confirmación en el botón, en el diálogo de vínculo y en la
asignación rápida de la página de suscripción como casilla. Si el periodo de
prestación cubre todo el periodo, la línea encaja sin pregunta.

**Insolvencia y traspaso:** si una factura se anuló porque el cliente es
insolvente, registre el periodo en el tablero con «Registrar como impago»;
el diálogo de renuncia trae el motivo con el número de factura. Si otra
empresa continúa las licencias, use «Traspaso» en la suscripción: nuevo
titular y fecha efectiva. Si la fecha efectiva coincide con el inicio del
primer periodo abierto, al nuevo titular se le facturan también los periodos
abiertos; si es posterior, el registro renuncia, si se desea, a los periodos
abiertos anteriores como impago. Una fecha efectiva en el inicio de la
suscripción traspasa toda la suscripción al nuevo titular; si no, a partir
de la fecha efectiva se crea una cesión. Si una factura ya va a la empresa
sucesora, el tablero ofrece el traspaso directamente en esa factura.
Si ya hay periodos facturados al titular anterior, el registro rechaza una
fecha efectiva al inicio de la suscripción; debe estar entonces a partir del
primer periodo abierto.
