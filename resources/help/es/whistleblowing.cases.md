---
title: "Canal de denuncias – Gestión de casos"
topic: whistleblowing.cases
version: 3
keywords:
    - denunciante
    - canal ético
    - sistema interno de información
    - protección del informante
    - informante
    - gestionar denuncia
    - acuse de recibo
    - caso de cumplimiento
    - conflicto de intereses
    - acceso de emergencia
audience: []
modules:
    - module.compliance
related:
    - whistleblowing.portal
    - whistleblowing.report
    - admin.security
    - privacy.overview
---

Aquí tramita las denuncias recibidas de informantes internos y
externos. Encontrará la lista **Denuncias de informantes** en el menú
**Cumplimiento** → **Oficina de denuncias**. La autorización de la
oficina de denuncias (rol **Oficina de denuncias**) está deliberadamente
**separada** de la administración: ni siquiera los administradores
tienen acceso sin una asignación propia al caso. Cada acceso requiere el
permiso correspondiente **y** la asignación al caso concreto; no hay
excepciones para administradores.

Para acceder se requiere su propia autenticación de dos factores; sin
ella, WorkDiary le redirige a su configuración.

**Lista de casos**: la vista general solo muestra datos básicos
(**Número de caso**, **Categoría**, **Estado**, **Prioridad**,
**Recepción hasta**, **Respuesta hasta**), deliberadamente **sin vista
previa del contenido**. La categoría y la prioridad solo aparecen cuando
usted está asignado al caso («Visible tras la asignación»). El contenido
de cada caso está cifrado con una clave propia.

**Detalle del caso**: el expediente muestra **Información del caso**,
**Contenido de la denuncia**, **Responsable** y **Comunicación y
notas**. Según sus permisos, puede

- **Confirmar recepción** (plazo **Recepción hasta**: 7 días desde la
  recepción),
- en **Cambiar estado**, elegir el siguiente estado permitido y
  aplicarlo con **Establecer estado**, por ejemplo «Recibida» →
  «Recepción confirmada» → «Evaluación preliminar» → «En tramitación»
  (entretanto «A la espera del denunciante» o «Remitida») → «Cerrada –
  …»; el cierre exige una **Justificación**, que se guarda como nota
  interna,
- en **Asignar responsable**, añadir a una persona mediante su **ID de
  usuario** con un **Rol** (**Asignar**),
- registrar una **Nota interna** (**Guardar nota**; nunca visible para
  la persona denunciante),
- enviar un **Mensaje a la persona denunciante** (**Enviar**); aparece
  en su buzón protegido.

Los adjuntos que sube la persona denunciante se guardan cifrados; el
expediente no ofrece por ahora una descarga.

**Confidencialidad y conflictos**:

- **Declarar conflicto de intereses** (justificación opcional) le
  bloquea a usted mismo para el caso: su asignación termina de inmediato
  y no puede levantar el bloqueo por sí mismo.
- El expediente no ofrece por ahora marcar a personas afectadas ni un
  acceso de emergencia para otras personas.
- Cada paso del caso queda registrado de forma inalterable en el
  registro del caso.

**Eliminación**: cuando un caso está en el estado «Revisión del plazo de
conservación», aparece la tarjeta **Eliminación controlada**. **Eliminar
caso** y la confirmación **Eliminar definitivamente** destruyen la clave
del caso: contenido de la denuncia, mensajes, adjuntos y asignaciones se
pierden de forma irrecuperable; solo queda un justificante de borrado
sin contenido. Es irreversible. Si se opone un procedimiento o una
obligación de conservación, establezca en su lugar el estado «Bloqueo de
borrado (legal hold)».
