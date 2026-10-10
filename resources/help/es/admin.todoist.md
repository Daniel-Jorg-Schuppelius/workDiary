---
title: "Conexión con Todoist"
topic: admin.todoist
version: 2
keywords:
    - Todoist
    - sincronizar tareas
    - sincronización de tareas
    - conectar Todoist
    - asignación de proyectos
    - preflight
    - secciones
    - asignar responsables
    - kanban
    - conflicto de tareas
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - work.overview
    - projects.manage
    - admin.scheduler
---

La página **Todoist** sincroniza tareas entre WorkDiary y Todoist. Solo se
sincronizan los proyectos de Todoist que usted asigna expresamente a un
proyecto de WorkDiary o al kanban global; los conflictos llegan a la bandeja
de integración, y nada se sobrescribe ni se elimina sin aviso. Encontrará la
página en el menú del sistema (el engranaje **Sistema** en la cabecera) en
**Plugins** → **Todoist**, en cuanto el plugin esté activo.

## Requisitos

- El plugin está activado para su organización: **Sistema** → **Plugins** →
  **Plugins** y luego **Activar** en la entrada Todoist.
- La página está reservada a los administradores.
- Se necesita una app de Todoist registrada. O bien el operador de su
  instalación ha guardado una, o bien introduce usted la suya: en la página
  **Plugins** mediante **Configurar** en la entrada Todoist, en los campos
  **ID de cliente (app propia de Todoist)** y **Secreto de cliente**. Si
  quedan vacíos, se aplica la app de la instalación. Su propia app debe
  registrar en Todoist, como URI de redirección, la dirección de su
  instalación de WorkDiary con la ruta `/admin/todoist/oauth/callback`.
- Por organización existe exactamente una conexión con Todoist y, por tanto,
  una sola cuenta de Todoist.

## Conectar con Todoist

1. Abra la página **Todoist**. La sección **Conexión** indica de antemano qué
   datos se transfieren: títulos, descripciones, estados, vencimientos y
   responsables de las tareas asignadas. WorkDiary no solicita permisos de
   borrado.
2. Haga clic en **Conectar con Todoist** e inicie sesión en Todoist. Tras
   conceder el acceso, vuelve a la página.
3. Después la página muestra **Estado**, **Cuenta**, **Conectado desde** y
   **Última sincronización**. **Renovar conexión** vuelve a iniciar la sesión
   y **Desconectar** termina la conexión; las asignaciones y los vínculos se
   conservan.

## Asignar proyectos

Con una conexión activa aparece la tabla **Asignaciones de proyectos**. Debajo
de la tabla crea una asignación nueva:

1. **Proyecto Todoist**: selección de su cuenta de Todoist.
2. **Destino**: **Proyecto WorkDiary** (elija entonces el proyecto; la lista
   muestra como máximo 500 proyectos) o **Kanban global** para tareas sin
   proyecto.
3. **Dirección**: **Todoist → WorkDiary**, **WorkDiary → Todoist** o
   **Bidireccional**.
4. **Asignar**. Cada asignación nueva empieza como **Borrador** y todavía no
   sincroniza nada.

En la tabla abre el **Preflight** de cada asignación, la cambia con
**Activar** o **Pausar** y la elimina con el icono de la papelera (las
referencias se conservan). La columna **Última ejecución** muestra la hora y
los contadores: creadas, actualizadas, sin cambios y conflictos.

## Preflight: responsables y secciones

Antes de la activación, el **Preflight** muestra lo que encontrará la
sincronización:

- **Indicadores**: tareas activas, subtareas, tareas recurrentes,
  vencimientos con hora, responsables no asignables y tareas ya vinculadas.
  Las tareas recurrentes llegan como una sola tarea con el próximo
  vencimiento; solo Todoist conoce la recurrencia. En los vencimientos con
  hora, WorkDiary solo adopta la fecha.
- **Asignación de responsables**: para cada colaborador de Todoist elige un
  usuario de WorkDiary y hace clic en **Guardar**. Una dirección de correo
  igual solo aparece como **Sugerencia**; la asignación vale únicamente tras
  su elección. Sin asignación, una tarea queda sin responsable.
- **Secciones → estado**: para cada sección de Todoist elige **Abierta** o
  **En curso**. Las secciones sin asignar dejan el estado intacto.

Solo entonces pone en marcha la asignación con **Activar**.

## Qué se sincroniza

- **Todoist → WorkDiary**: cada tarea activa de Todoist se convierte en una
  tarea de WorkDiary en el proyecto de destino o en el kanban global. Se
  sincronizan título, descripción, prioridad (Todoist p1 a p4 corresponden a
  Urgente, Alta, Media, Baja), vencimiento, duración como presupuesto de
  tiempo, responsable y estado. Completada en Todoist significa aquí
  **Hecho**. Las subtareas quedan bajo su tarea principal.
- **WorkDiary → Todoist**: las tareas nuevas creadas tras la activación en el
  proyecto asignado o en el kanban global las crea WorkDiary en Todoist.
  También se transmiten los cambios de las tareas vinculadas; un cambio de
  estado mueve la tarea a la sección asignada o la completa o la reabre. Las
  tareas que ya existían antes de la activación no se transfieren.
- **Bidireccional** combina ambas direcciones.
- En las tareas vinculadas, el diálogo de la tarea muestra el enlace **Abrir
  en Todoist**.

## Cuándo se sincroniza

- Cada hora, WorkDiary obtiene de Todoist los cambios desde la última
  ejecución. La frecuencia se cambia en **Tareas programadas**.
- **Sincronizar ahora** inicia una sincronización completa en segundo plano.
  Solo esta detecta también las tareas que desaparecieron del proyecto de
  Todoist sin eliminarse, por ejemplo porque se movieron.
- Los cambios desde WorkDiary salen de inmediato mediante una cola y se
  repiten si hay errores.
- Si usa su propia app de Todoist, puede introducir allí además un webhook
  hacia la dirección de su instalación con la ruta `/api/webhooks/todoist`.
  Este inicia una sincronización dirigida cuando hay cambios; la ejecución
  horaria sigue siendo la fuente fiable.

## Conflictos y eliminaciones

- WorkDiary compara cada campo con su estado en la última sincronización. Si
  un campo se cambió de forma distinta en ambos lados, se crea un conflicto en
  la bandeja; allí decide qué estado vale. Hasta entonces, WorkDiary no
  transmite ese campo.
- WorkDiary no transmite eliminaciones en ninguna dirección. Si una tarea
  desaparece en Todoist o se elimina aquí una tarea vinculada, se crea un caso
  en la bandeja.
- Una subtarea cuya tarea principal falta aquí también acaba en la bandeja.
- Si una tarea se completó en WorkDiary, reabrirla en Todoist no la
  restablece.

**Bandeja de integración** en la página abre la Bandeja de conciliación
filtrada por Todoist.

## Errores frecuentes

- «Todoist no está configurado»: no hay ninguna app de Todoist guardada, ni
  por el operador ni en los ajustes del plugin.
- «Estado OAuth no válido o caducado»: el inicio de sesión tardó demasiado o
  se hizo en otra sesión. Vuelva a conectar.
- «Falló el intercambio de token»: el ID de cliente, el secreto de cliente o
  la URI de redirección de su propia app no son correctos.
- La lista de proyectos de Todoist está vacía: la conexión no llega a
  Todoist. Compruebe el estado en la página **Plugins** y renueve la
  conexión.
- Las tareas llegan sin responsable: el colaborador de Todoist aún no está
  asignado a un usuario en el preflight.
- No se sincroniza nada: la asignación sigue en **Borrador** o **En pausa**.
- La conexión muestra **En pausa**: Todoist rechazó el acceso, por ejemplo
  porque se revocó la autorización de la aplicación en Todoist. La
  sincronización está en pausa; vuelva a conectar con **Renovar conexión**.
  Si una sincronización falla por otro motivo, la página indica el último
  error hasta que una sincronización vuelva a funcionar; además se crea una
  tarea operativa.
