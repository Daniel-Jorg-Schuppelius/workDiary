---
title: "Existencias y escaneo"
topic: inventory.stock
version: 2
keywords:
    - stock
    - almacén
    - entrada de mercancía
    - salida de stock
    - traslado de stock
    - reserva
    - punto de pedido
    - stock mínimo
    - bloquear lote
    - gestión de lotes
    - FEFO
    - escanear código de barras
    - valor del stock
audience: []
modules:
    - module.lager
related:
    - warehouses.manage
    - inventory.counts
    - inventory.labels
    - articles.master
---

La vista de existencias muestra por almacén las cantidades disponibles,
físicas y reservadas de cada variante, el precio medio móvil, el valor
del stock y el punto de pedido. Con permiso de contabilización
registra movimientos manuales (entrada, salida, reserva, liberación) y
define stocks mínimos y de aviso por variante y almacén; las salidas a
negativo solo son posibles si las permite expresamente. Los lotes se
gestionan en la lista de lotes, donde pueden dividirse y fusionarse. La
vista de escaneo resuelve un código (número de serie, lote, GTIN o SKU)
y contabiliza directamente una acción. Todos los movimientos se
escriben en el diario continuo y no son reversibles; las correcciones
se hacen mediante contraasientos.

Un lote puede **bloquearse** y volver a **liberarse** en la lista de lotes —
siempre con un motivo; ambas acciones quedan en el registro de auditoría. Las
existencias de un lote bloqueado permanecen en el almacén, pero la lista de
picking deja de proponerlo; dividir y fusionar solo es posible tras la
liberación. Al fusionar, las existencias del lote de origen pasan al lote de
destino mediante contraasientos (traslado salida/entrada); el lote fusionado
queda cerrado y ya no admite entradas.

**Salida por lote.** Si el almacén tiene existencias en lotes, el
formulario de movimientos ofrece el campo «Lote (salida)»: si lo deja
vacío, la salida sigue FEFO (primero la fecha de caducidad más próxima,
como en la lista de picking); de lo contrario se retira exactamente el lote
elegido. Para entrada, reserva y liberación el campo no tiene efecto. Los
artículos con lote pueden retirarse así mientras sus lotes cubran la
cantidad — para ellos no se retiran existencias sin lote; la entrada sigue
pasando por la recepción de mercancías. Un traslado por escaneo lleva los
lotes al almacén de destino; un código de lote escaneado contabiliza
exactamente ese lote.

**Bloqueo en las existencias.** Bloquear un lote contabiliza sus
existencias en el estado «bloqueado»: permanecen en el almacén, pero dejan
de contar como disponibles y aparecen en la vista de existencias en la
columna «Bloqueado». Un lote bloqueado no admite recepciones; las
devoluciones y reubicaciones en él siguen bloqueadas. La liberación
contabiliza de vuelta las existencias bloqueadas. Dividir un lote también
es un asiento: la cantidad dividida pasa al nuevo lote en el diario de
movimientos.

**Depurar existencias antiguas.** Hasta octubre de 2026 las salidas no
llevaban lote; por eso las existencias de un lote pueden superar lo que
realmente queda de él. Su administrador lo comprueba con el comando
`inventory:lots:repair`: sin opciones es una simulación con una tabla por
lote (saldo contable, resto de las capas de valoración, diferencia); con
`--apply` traspasa la diferencia a «sin lote» en valoración FIFO o FEFO, y
las existencias totales no cambian. Con precio medio móvil solo informa del
lote; los lotes bloqueados, solo tras su liberación.
