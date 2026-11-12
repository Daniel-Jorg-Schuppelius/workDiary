---
title: "Activos y flota"
topic: assets.fleet
version: 9
keywords:
    - gestión de flota
    - gestión de vehículos
    - inventario
    - equipos
    - préstamo de equipos
    - entrega de herramientas
    - devolución
    - registro de combustible
    - registro de carga
    - mantenimiento
    - reportar avería
    - ciclo de vida
audience: []
modules:
    - module.fuhrpark
related:
    - documents.manage
    - travel-expenses.manage
    - reports.overview
---

Los activos y vehículos representan objetos operativos con su estado,
responsabilidades, documentos e información de mantenimiento. Los
registros de repostaje y de carga completan el historial de consumo.

Registre los datos maestros y los identificadores únicos, asigne la
ubicación o los responsables y mantenga los intervalos de
mantenimiento y los documentos relevantes. Los cambios de estado deben
reflejar el ciclo de vida real.

Antes de eliminar o dar de baja un activo, compruebe si tiene
vinculados mantenimientos, expedientes, trayectos o documentos
abiertos. El historial crítico debe archivarse y no perderse por
sobrescritura.

## Categorías

El campo «Categoría» ofrece las categorías informáticas generales y las de su
organización. Los perfiles sectoriales aportan categorías adecuadas, por
ejemplo cámara de inspección y prensadora en climatización o pulsioxímetro y
armario de estupefacientes en cuidados. Los administradores crean sus propias
categorías en Clasificaciones, dominio «Categorías de activos». Una categoría
desactivada ya no se ofrece para activos nuevos, pero se mantiene en los que
ya la tienen.

## Asignación y devolución

Mediante el panel «Asignación / devolución» de la página de detalle del
activo, un equipo se asigna a una persona o a un equipo de trabajo,
opcionalmente con referencia a una orden y una fecha de devolución
prevista. Por cada activo existe como máximo una asignación abierta; un
activo ya asignado o bloqueado por un defecto no puede volver a
asignarse. Con la devolución, el activo vuelve a estar disponible. Si
una asignación supera la devolución prevista, aparece un aviso de
retraso y el escáner de plazos notifica a la persona que lo tiene en
préstamo o a la jefatura de equipo.

## Defectos y bloqueos

En el panel «Defectos / bloqueos» pueden registrarse deficiencias con
su nivel de gravedad. Si está marcada la opción «Bloquear activo (sin
retiro posible)», el defecto abierto impide cualquier nueva asignación
hasta que se resuelva o se dé de baja. Para resolverlo o darlo de baja
es obligatoria una nota de resolución.

## Planes de mantenimiento

En la tarjeta **Planes de mantenimiento**, **Crear plan** registra
mantenimientos periódicos: **Denominación**, **Intervalo** con **Valor**,
**Tolerancia (días)** y **Primer vencimiento**. Para días, semanas y
meses, **Hecho** calcula el siguiente vencimiento a partir del
intervalo; para horas de funcionamiento y kilómetros lo introduce usted
mismo. **Pausar** saca el plan de la comprobación de vencimientos.

Quien puede ver los contratos SLA vincula el plan a un **Contrato SLA**
(un contrato del cliente del activo o uno sin cliente) y marca
**Obligación contractual** cuando el plan representa una cita
obligatoria de ese contrato. El plan aparece entonces en el contrato en
**Mantenimientos obligatorios por contrato**; la marca también se
muestra en el activo, en el expediente del objeto y en el portal del
cliente. Si el helpdesk está activado, **Al vencimiento** define lo que
hace la comprobación diaria de vencimientos: **Crear un ticket de
servicio** crea exactamente un ticket por vencimiento con el contrato
elegido, **Solo aviso (sin registro)** solo anota el vencimiento.

## Contrato SLA del activo

Quien puede ver los contratos SLA elige un **Contrato SLA** para el activo en
**Contratos & SLA** del formulario **Editar**: un contrato del cliente guardado
o uno sin cliente. Los OLA, los contratos vinculados a un proyecto y los
contratos inactivos no se ofrecen. El contrato se aplica a los tickets de
servicio de este activo antes que los contratos del proyecto y del cliente
(tema «SLA, contratos y niveles de servicio»); figura en la cabecera de la
página de detalle y en el expediente del objeto como **Asignación directa**. Sin
el permiso para ver contratos SLA, el campo no aparece y la vinculación no
cambia al guardar.

## Expediente del objeto (ciclo de vida)

El «Expediente del objeto» reúne todo el ciclo de vida de un activo en
una vista coherente e imprimible: datos maestros, ubicación y sala,
estado del ciclo de vida derivado (en servicio, sustituido o fuera de
servicio), puesta en servicio, retirada de servicio y garantía. Debajo
aparecen los mantenimientos, las asignaciones y devoluciones, los
defectos y bloqueos, las órdenes vinculadas, los protocolos, los
consumos de material, los puntos abiertos y los adjuntos, así como el
historial completo del ciclo de vida.

El expediente es accesible mediante el botón «Expediente del objeto» de
la página de detalle del activo y puede emitirse como documento con la
función de impresión del navegador (añadir «?print=1» abre
directamente el cuadro de diálogo de impresión). El estado del ciclo de
vida se deriva del estado, de la retirada de servicio y de la garantía;
no existe un mantenimiento independiente.

## Dar de baja

**Dar de baja**, en la página de detalle del activo, retira definitivamente
un activo del servicio. La fecha y el motivo son obligatorios; ambos figuran
después en la cabecera de la página de detalle y en el expediente del objeto.
Recoja antes un activo que siga entregado. El paso pausa los planes de
mantenimiento, suspende las obligaciones de inspección y cancela las citas de
inspección abiertas. Después el activo ya no se puede entregar, alquilar,
reservar ni utilizar en un procedimiento, y deja de aparecer en las listas de
selección de alquiler y de órdenes de impresión. El expediente con todos los
justificantes se conserva. El paso no se puede deshacer; el estado «Fuera de
servicio» no se ofrece en el formulario «Editar».

**Permiso:** **Dar de baja activos/objetos**.

## Cambio de propietario

**Cambiar propietario**, en la página de detalle del activo, registra un
cambio de propiedad (organización, cliente, externo) y de cliente, con una
nota si lo desea. Cada cambio figura en el expediente del objeto, en
«Historial de cambios de propietario», con fecha, origen, destino, persona y
nota; las entradas no se pueden modificar. Elegir otro cliente en el
formulario «Editar» es el mismo cambio: se registra con la nota introducida
allí.

Si cambia el cliente, se eliminan las vinculaciones con contratos del cliente
anterior, en el activo y en sus planes de mantenimiento, allí junto con la
**Obligación contractual**; los contratos sin cliente se mantienen. Elija un
contrato del nuevo cliente después de guardar, en el formulario abierto de
nuevo.

**Permiso:** **Transferir la propiedad de los activos**, también para el
cambio de cliente en el formulario «Editar».

## Estado, bloqueo y condición

Los estados **Bloqueado** y **En reparación** bloquean un activo en todos los
lugares donde también actúa un bloqueo de inspecciones o de alquiler:
entrega, alquiler, reserva de vehículo, orden de impresión e inicio de un
procedimiento en el activo o en una orden relativa a ese activo. Una
plantilla de procedimiento que permite expresamente activos bloqueados (por
ejemplo, la reparación) se inicia de todos modos. Para ello el estado crea un
bloqueo con el motivo «Bloqueado manualmente» o «Mantenimiento». Termina
cuando usted restablece el estado o levanta el bloqueo en **Equipo de
inspección**; el activo vuelve entonces a estar **Activo**. Las excepciones
temporales se aplican a este bloqueo como a cualquier otro.

La **condición** que aparece junto al estado se deriva y no se mantiene a
mano: **Crítico** con un bloqueo activo o un defecto bloqueante, **Limitado**
con un defecto abierto o con mantenimiento o inspección vencidos; en los
demás casos, **Correcto**. Se actualiza con cada cambio y por la noche.

Si cambia la ubicación en texto libre o la sala, el cambio figura en la
cronología como «Ubicación cambiada» con la ubicación anterior y la nueva.

## Recogida con condición

**Retirar** abre un cuadro de diálogo para el **Estado en la devolución** y
la **Nota**. Ambos figuran después en la tabla de historial del panel
«Asignación / devolución», junto al estado en la entrega, y en el expediente
del objeto.

## Cockpit

Debajo de las secciones de la página de detalle, los módulos muestran lo que existe para el activo: **tickets de servicio** abiertos, **leasing y financiación** (contratos a los que pertenece el activo), **reclamaciones** abiertas, **plazos de garantía** abiertos de pedidos y protocolos del activo, **puntos abiertos** y **procedimientos**. Un panel solo aparece si el módulo está activado, usted tiene el derecho a la lista correspondiente y hay entradas; el clic abre la entrada.

**Volver a poner en servicio:** un activo fuera de servicio se vuelve a poner en servicio con la acción del mismo nombre, con un motivo y una entrada en el registro de auditoría, con el mismo permiso que la baja. Los planes de mantenimiento pausados y las obligaciones de inspección inactivas siguen desactivados y se reactivan uno a uno. Un activo eliminado mediante una orden de eliminación sigue fuera de servicio; el formulario de edición no cambia este estado.

## Lista de piezas

La **lista de piezas** de un equipo recoge lo que lleva montado esta unidad:
artículo o texto libre, cantidad, ubicación, número de serie, fecha de montaje e
intervalo de sustitución. Sustituir crea una pieza nueva y deja la antigua en el
historial con su fecha de desmontaje; las piezas de desgaste vencidas aparecen
como propuesta. En **Material de las intervenciones** figuran los consumos de
material asignados a este equipo en un parte de horas. **Adoptar** añade un
material a la lista con el día de la intervención como fecha de montaje, con
artículo si el material lo tiene; cada consumo solo puede adoptarse una vez.
