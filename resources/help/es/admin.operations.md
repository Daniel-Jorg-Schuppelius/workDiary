---
title: "Tareas operativas y ventanas de mantenimiento"
topic: admin.operations
version: 1
audience:
    - admin
related:
    - admin.backups
    - admin.diagnostics
---

WorkDiary apoya la operación continua con dos herramientas: el **centro
de tareas** para comprobaciones operativas recurrentes y la
planificación de **ventanas de mantenimiento**.

**Comprobaciones operativas como escaneo:** Un escaneo recurrente (por
defecto cada hora) comprueba puntos relevantes para la operación — como
certificados a punto de expirar, copias de seguridad ausentes y fechas
de caducidad de licencias y credenciales. Los puntos detectados aparecen
como tareas priorizadas en el centro de tareas, ordenadas por gravedad
(crítico antes que advertencia antes que aviso) y filtrables por estado,
tipo y gravedad.

**Gestionar tareas:** Cada tarea puede **completarse**, **posponerse**
(snooze durante un número de días configurable), **delegarse** (a una
persona de su organización) o **ignorarse** (con justificación
obligatoria). Las tareas completadas pueden reabrirse. Todos los cambios
de estado se registran de forma inalterable a efectos de auditoría. La
visibilidad está vinculada a la organización; las tareas de ámbito de
instalación residen en la organización del operador y están marcadas
como tales.

**Planificar ventanas de mantenimiento:** Las ventanas de mantenimiento
se anuncian con inicio, fin y un plazo de preaviso opcional — desde el
momento del anuncio los usuarios ven un mensaje con su texto
informativo. Una ventana se aplica a elección **a todo el sistema** o
solo a la **organización** actual.

**Desarrollo de una ventana:** Tras planificarse, una ventana recorre
los pasos anunciar, iniciar, prolongar si es necesario y finalizar.
Durante la ventana en curso puede activar opcionalmente el **modo de
solo lectura** (los usuarios ven los datos pero no cambian nada) y
**bloquear la entrada de datos** (las entregas externas quedan en
espera). Si algo sale mal, un **rollback** documenta la cancelación con
sus notas; las ventanas planificadas también pueden anularse sin
sustitución. Cada acción queda auditada.

**Recomendación:** Planifique las ventanas de mantenimiento con
suficiente antelación para que el anuncio surta efecto, y trabaje el
centro de tareas con regularidad — las tareas críticas primero. Posponer
está pensado para aplazamientos conscientes, no como estado permanente.
