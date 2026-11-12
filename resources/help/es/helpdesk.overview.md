---
title: "Helpdesk y Service Desk"
topic: helpdesk.overview
version: 5
keywords:
    - sistema de tickets
    - crear ticket
    - incidencia
    - solicitud de soporte
    - solicitud de servicio
    - cola de soporte
    - SLA
    - tiempo de respuesta
    - nota interna
    - enrutamiento de tickets
    - satisfacción del cliente
    - ITSM
audience: []
related:
    - open-issues
    - customer-portal.overview
---

El helpdesk agrupa incidencias y solicitudes de servicio como tickets —
cada uno con número, título, prioridad, estado, cliente, referencia
opcional a un equipo/dispositivo y persona responsable.

**Colas:** Los tickets se gestionan en colas (áreas de responsabilidad),
cada una con su equipo responsable y un contrato SLA opcional.
Exactamente una cola es la cola estándar para los tickets entrantes
nuevos; el cambio se realiza de forma controlada. Una cola solo puede
eliminarse cuando ya no tiene tickets asignados — nada se reasigna en
silencio.

**Prioridades y SLA:** Del contrato SLA se derivan plazos de reacción y
de resolución por prioridad. Los plazos en curso figuran de forma
visible en el ticket; si un plazo se supera sin que la primera reacción
o la resolución se hayan producido a tiempo, se registra como
incumplimiento y se incorpora al análisis de SLA.

**Público frente a interno:** Las respuestas al cliente y las notas
internas son dos acciones separadas con permisos distintos. Una
respuesta pública es visible para el cliente y puede enviarse por correo
electrónico a los destinatarios; una nota interna permanece
exclusivamente en el equipo. La separación está anclada técnicamente —
la publicación accidental de anotaciones internas queda excluida.

**Entrada:** Los tickets se crean manualmente, por correo electrónico
(las respuestas a un ticket existente se asignan automáticamente al
expediente), a través del portal de clientes, a partir de puntos
abiertos, desde planes de mantenimiento o mediante la interfaz. La
fuente queda anotada en el ticket.

**Enrutamiento:** Las reglas distribuyen automáticamente los tickets
entrantes — por ejemplo a una cola, con prioridad o responsabilidad — y
se aplican en un orden definido. Un modo de prueba verifica una regla
contra un ticket de ejemplo y registra el resultado sin cambiar nada.

**Aprobaciones:** Las etapas de aprobación abiertas de solicitudes de
servicio, cambios y negociaciones contractuales se reúnen en
«Aprobaciones» — por expediente solo la siguiente etapa abierta y solo para
los responsables: una persona, un rol o, en las negociaciones
contractuales, el rol que la organización asigna al tipo de etapa (por
defecto: comercial → Contabilidad, técnica → Jefe de equipo, RR. HH. →
Administración de personal). Aprobar, rechazar, consultar y delegar tienen
el mismo efecto que en el expediente; un rechazo cierra la ronda y la
autoaprobación sigue bloqueada.

**Servicios de negocio y mapa de servicios:** Un ticket puede llevar un
servicio de negocio (por ejemplo «correo» o «servidor de contabilidad») y una
oferta de servicio del catálogo; después los tickets se filtran, enrutan y
evalúan en los informes por ellos. El mapa de servicios muestra cómo dependen
los servicios de negocio entre sí: por cada servicio, de qué depende, quién lo
necesita y quién se vería afectado por una caída — junto con los tickets
abiertos de esos servicios. Las dependencias se mantienen en el servicio de
negocio (selección múltiple); un ciclo se rechaza. En el ticket, la tarjeta
«Impacto» muestra a qué otros servicios de negocio afectaría una caída del
servicio notificado y cuántos tickets hay abiertos allí — así se prioriza una
incidencia antes de que lleguen los avisos consecutivos.

**Satisfacción e informes:** Tras el cierre, el cliente puede emitir una
valoración breve en el portal — una por ticket. Los informes muestran el
volumen por cola, los tiempos de reacción y resolución, el cumplimiento
de SLA, los motivos de espera, las tasas de cambios, el inventario de
problemas y la demanda del catálogo. Se renuncia deliberadamente a
clasificaciones de agentes individuales.

**Cambiar el estado:** con **Cambiar estado** en el ticket usted elige el
nuevo estado; solo se ofrecen las transiciones permitidas. Al resolver y
cerrar registra el **Código de cierre** y el **Resumen de la solución**. Un
estado de espera necesita un motivo, una fecha de seguimiento y una persona
responsable; llegada la fecha, WorkDiary se lo recuerda. El contrato SLA
determina si el reloj SLA se detiene. Un ticket resuelto solo se reabre con
un motivo; se conservan los tiempos de reacción y de solución anteriores.

**Ajustes de la cola:** en cada cola usted define los tipos de ticket
admitidos, el horario de atención, la región de festivos, el nombre del
remitente y la dirección de respuesta. Los tickets nuevos sin asignación van
a la cola predeterminada; si esta no admite el tipo de ticket, a la primera
cola que lo admita. El SLA predeterminado de la cola se aplica si ni el
activo del ticket, ni el proyecto ni el cliente tienen un contrato propio.

**Cola gestionada externamente:** si un sistema conectado como Zammad lleva
los tickets de una cola, los cambios de estado y las respuestas al cliente
quedan bloqueados en WorkDiary y el ticket muestra un aviso. La asignación,
las notas internas, los tiempos y la facturación siguen siendo posibles.
Cuando usted restablece el destino de tickets de la integración, WorkDiary
vuelve a llevar la cola.

**Confidencialidad:** un ticket confidencial solo lo ven el responsable, los
observadores, el equipo de la cola y los administradores. Usted fija el nivel
al crear el ticket o en la clasificación.

**Pedir un servicio:** en **Pedir un servicio** los empleados piden para sí
mismos entradas del catálogo de servicios. Los datos del formulario de pedido
aparecen en el ticket y en el diálogo de decisión de la aprobación; el ticket
muestra además qué expediente cumple el pedido.

**Estado del envío:** en cada respuesta enviada por correo electrónico se
indica si el envío está pendiente, realizado o fallido.

**Ticket desde un punto abierto:** en el punto abierto, **Abrir ticket** crea
un ticket con el título, la descripción y la referencia del punto. Por cada
punto se crea como máximo un ticket; el punto remite a él.

**Portal del cliente:** cuando el cliente confirma la solución o reabre el
ticket con un motivo, esto funciona como un cambio de estado interno — con
historial y sin alterar los tiempos de reacción y de solución. En las colas
gestionadas externamente, la respuesta, la aceptación y la reapertura también
están bloqueadas en el portal; allí el portal solo muestra el estado.

**Cambio de cola y buzón:** cuando un ticket cambia de cola, se aplica el SLA
predeterminado de la nueva cola si su contrato procedía hasta entonces de la
cola o de la organización, o si faltaba; un contrato del activo, del
proyecto o del cliente y un contrato fijado expresamente se mantienen. En **Buzón de
entrada** usted asigna un buzón a la cola — uno por cola.

**Tickets confidenciales en listas:** las listas de selección y las
referencias muestran un ticket confidencial solo a las personas autorizadas.
En los desgloses de informes sigue contando, pero para los demás aparece solo
como «Ticket confidencial».
