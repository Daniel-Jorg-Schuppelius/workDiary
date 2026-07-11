---
title: "Helpdesk y Service Desk"
topic: helpdesk.overview
version: 1
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

**Satisfacción e informes:** Tras el cierre, el cliente puede emitir una
valoración breve en el portal — una por ticket. Los informes muestran el
volumen por cola, los tiempos de reacción y resolución, el cumplimiento
de SLA, los motivos de espera, las tasas de cambios, el inventario de
problemas y la demanda del catálogo. Se renuncia deliberadamente a
clasificaciones de agentes individuales.
