---
title: "Existencias y escaneo"
topic: inventory.stock
version: 7
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

## Unidades, diario de almacén y ajustes

**Registrar en una unidad del artículo.** Si un artículo tiene otras unidades con factor de conversión (por ejemplo, 1 rollo = 100 m), el formulario de movimientos y la vista de escaneo ofrecen la selección **Unidad** junto a la cantidad. Siempre se contabiliza en la unidad base; la cantidad introducida y su unidad quedan en el movimiento. El redondeo es comercial a cuatro decimales. Se rechazan una cantidad que equivale a cero en la unidad base y una unidad no definida en el artículo.

**Diario de almacén.** La pestaña **Diario de almacén** lista todos los movimientos con fecha y hora, almacén, tipo de movimiento, cantidad en unidad base y la columna **Registrado como** (cantidad y unidad introducidas). La búsqueda, el almacén y el periodo superior acotan la lista.

**Caducidad.** Si un artículo tiene la propiedad «caducidad obligatoria», el almacén solo acepta su entrada mediante la recepción de mercancía de un pedido, con lote y fecha de consumo preferente. Se rechazan las entradas manuales y por escaneo. Un lote ya conocido aporta su fecha.

**Ajustes de almacén.** Con el permiso de configuración de almacén, usted abre los **ajustes de almacén** en la vista de existencias. Allí elige el **método de valoración** de la organización (promedio móvil, FIFO o FEFO) y el **momento de la reserva** en fabricación (al liberar o al iniciar). Al cambiar a FIFO o FEFO, las existencias reciben una capa inicial al precio medio anterior. Solo se puede volver al promedio móvil cuando ya no queden existencias en capas, porque el promedio no se actualizó durante ese tiempo.

## Permisos, reservas y números de artículo

**Permisos separados.** Contabilizar movimientos, contar inventario, aprobar diferencias de inventario, modificar reservas y compensar asientos externos son permisos independientes. Quien podía contabilizar conserva contar, reservar y compensar; su administrador puede separar los permisos por rol.

**Modificar una reserva.** Con el permiso de reservas, el icono de lápiz de una reserva activa abre un diálogo con la cantidad reservada, retirada y pendiente. Allí usted fija la prioridad (un número menor significa mayor prioridad) y libera una cantidad parcial, por ejemplo para asignarla a otra orden.

**Aprobación de cuatro ojos.** En los ajustes de almacén puede activarse la aprobación de cuatro ojos para diferencias de inventario, con un umbral para el valor de existencias de la diferencia por línea. A partir del umbral no aprueba quien contó. El umbral y quien aprueba constan en el registro de auditoría.

**SKU y GTIN.** Los ajustes de almacén muestran quién gestiona el número de artículo, el SKU de las variantes y el GTIN. Se elige en la página «Rangos de numeración», rango «Artículo». Si un sistema de gestión de mercancías gestiona los números, los campos quedan bloqueados en el maestro de artículos y llegan con la sincronización.

**Existencias negativas a cuatro ojos:** si la organización exige en la configuración de almacén una aprobación a cuatro ojos para salidas a existencias negativas, una salida con «Permitir existencias negativas» se convierte en una solicitud en la bandeja de aprobaciones. Solo se registra cuando otra persona con el permiso «Autorizar existencias negativas» da su conformidad; solicitante y aprobador quedan en el registro de auditoría.

**Consumo de material en el parte de horas:** el material vinculado a un artículo con gestión de existencias registra, al anotarlo en el parte de horas o en «Hoy», la salida del almacén marcado como «Predeterminado» (si hay varios, el primero por nombre). El movimiento aparece en el diario de almacén con la cantidad y la unidad introducidas; un cambio registra la diferencia y eliminarlo lo devuelve. Rigen las reglas de esta página: existencias negativas solo con el permiso y, en su caso, cuatro ojos, lotes por fecha de caducidad, números de serie solo a través del almacén. El material que hasta ahora retiraba además aquí, en adelante regístrelo solo en el parte de horas; de lo contrario la salida cuenta dos veces.

**Redistribuir reservas:** en el diálogo «Modificar reserva» cede la cantidad abierta a otra reserva del mismo artículo, almacén y propiedad, por ejemplo a una orden con mayor prioridad. Una reserva más reciente nunca desplaza en silencio a una más antigua; la redistribución queda en el registro de auditoría y la línea de material de una orden de fabricación la sigue.

**Valor del stock según el método:** El precio medio y el valor del stock de
la vista siguen el método de valoración del artículo: con el precio medio móvil,
del libro de medias; con FIFO y FEFO, de las capas de valoración abiertas (valor
de las cantidades restantes, media = valor ÷ cantidad). La clasificación ABC del
inventario cíclico usa los mismos valores.
