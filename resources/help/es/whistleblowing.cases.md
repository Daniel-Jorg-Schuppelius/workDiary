---
title: "Canal de denuncias – Gestión de casos"
topic: whistleblowing.cases
version: 5
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

Los adjuntos que sube la persona denunciante se guardan cifrados y se
analizan en busca de software malicioso antes de liberarlos. La tarjeta
**Adjuntos** indica el nombre y el tamaño de cada archivo; los adjuntos
liberados se descargan con el icono **Descargar**. En otro caso, la tarjeta
muestra el estado del análisis: **Análisis pendiente** mientras falta el
resultado, **Rechazado** o **Análisis fallido**; WorkDiary no entrega estos
archivos. Sin un antivirus configurado, los adjuntos quedan pendientes.

**Confidencialidad y conflictos**:

- **Declarar conflicto de intereses** (justificación opcional) le
  bloquea a usted mismo para el caso: su asignación termina de inmediato
  y no puede levantar el bloqueo por sí mismo.
- **Marcar a la persona afectada** bloquea a una persona para el caso por
  su **ID de usuario** (**Marcar**, nota opcional); las asignaciones
  existentes terminan de inmediato.
- **Acceso de emergencia** da a una persona no asignada acceso temporal,
  cuatro horas por defecto (ID de usuario y justificación obligatoria,
  **Liberar**). Solo otra persona puede concederlo.
- **Exportar caso** genera un paquete cifrado con AES-256 (justificación y
  una contraseña de al menos doce caracteres, **Exportar**); entregue la
  contraseña por separado del paquete.
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

**Filtros de la lista**: la barra sobre la lista filtra por **estado**, **categoría**, **prioridad**, persona asignada, estado de los plazos («Plazo vencido», «Plazo en 14 días», «Respuesta pendiente») y periodo de entrada. Categoría y prioridad solo se aplican a los casos que tiene asignados; de lo contrario, la lista de resultados revelaría ambas.

**Plazos y respuesta**: si al enviar un mensaje marca **Esta es la respuesta conforme al art. 17 HinSchG**, WorkDiary registra el momento en el caso; una simple consulta no cuenta. A partir de entonces cesa el recordatorio del plazo de respuesta. Los gestores asignados reciben recordatorios sin contenido: 48 horas antes de vencer el acuse de recibo, 14 días y 48 horas antes del plazo de respuesta (una vez cada uno) y después a diario mientras un plazo esté vencido. Pasar un caso directamente de «Recibido» a «Revisión» confirma la recepción.

**Otras acciones en el expediente**:

- **Revocar asignación** (icono junto al gestor): la persona pierde el acceso de inmediato.
- **Añadir un documento del canal de denuncias**: el archivo se cifra como un adjunto de la persona informante y solo se libera tras el análisis; la persona informante no lo ve.
- El estado «Bloqueo de eliminación» exige **base jurídica**, **responsable** y **fecha de revisión**; en esa fecha WorkDiary avisa a los gestores.
- Una reapertura (de «Cerrado – …» a «En curso») exige un **motivo**; el plazo de conservación vuelve a empezar con el siguiente cierre.
- **Registro de eventos**: con el permiso de consulta del registro verá quién abrió, modificó o exportó el caso y cuándo, sin contenido de la denuncia. Cada consulta del registro queda a su vez registrada.
