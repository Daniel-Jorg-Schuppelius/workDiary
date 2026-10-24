---
title: "Importación de tareas de Asana"
topic: admin.asana
version: 1
keywords:
    - Asana
    - importar tareas
    - espacio de trabajo
    - asignación de proyectos
    - vista previa
    - importación de tareas
    - caso por revisar
audience:
    - admin
related:
    - admin.plugins
    - admin.integration-inbox
    - admin.data-ownership
    - projects.manage
    - admin.scheduler
---

La página **Asana** importa tareas de proyectos de Asana seleccionados en sus
proyectos de WorkDiary. Así registra tiempos sobre el trabajo planificado en
Asana y lo factura como siempre. La importación solo lee: WorkDiary no escribe
nada en Asana. Encontrará la página en el menú del sistema (el engranaje
**Sistema**) en **Plugins** → **Asana**, en cuanto el plugin esté activo.

## Requisitos

- El plugin está activado para su organización: **Sistema** → **Plugins** →
  **Plugins** y luego **Activar** en la entrada Asana.
- El módulo Kanban tiene licencia; la página está abierta a los
  administradores.
- El operador de su instalación ha configurado la aplicación de Asana del
  editor. Si falta, la página lo indica; no se necesita ni se prevé un token
  de acceso personal.
- Cada organización tiene exactamente una conexión con Asana y un espacio de
  trabajo.

## Conectar y elegir el espacio de trabajo

1. Haga clic en **Conectar con Asana** e inicie sesión en Asana. WorkDiary solo
   solicita acceso de lectura a tareas, proyectos, espacios de trabajo y a su
   propia cuenta.
2. De vuelta en la página, elija el espacio de trabajo en **Elegir Espacio de
   trabajo** y haga clic en **Aplicar**.
3. Mientras existan asignaciones de proyectos, el espacio de trabajo no se
   puede cambiar.

## Asignar proyectos y revisar la vista previa

1. **Asignar proyecto** abre un diálogo: elija el **Proyecto de Asana** y el
   **Proyecto de WorkDiary** al que pertenecen las tareas.
2. La **Vista previa** muestra las tareas abiertas y lo que haría con ellas la
   primera sincronización. No guarda nada.
3. Vincule tareas existentes del proyecto de destino mediante **Vincular la
   homónima** o la sección **Vincular una tarea existente**. Los campos
   divergentes pasan a la bandeja de entrada para su revisión.
4. **Confirmar importación** libera la asignación. Solo entonces la
   sincronización crea las tareas abiertas. Las tareas terminadas nunca se
   crean.

## Qué se importa

- **Título**, **notas** como descripción y **vencimiento** como fecha. Si una
  tarea de Asana tiene hora de vencimiento, la fecha cuenta en la zona horaria
  de su organización.
- **Estado**: terminada en Asana → **Hecho**; si no, **Abierto**. Si la tarea
  está **En curso** en WorkDiary, sigue así: Asana no tiene estado de avance.
- Una tarea en varios proyectos asignados con el mismo destino se crea una
  sola vez. Si los proyectos apuntan a destinos distintos, WorkDiary no la
  crea e informa de un caso por revisar.
- Asana no reabre una tarea que usted terminó en WorkDiary.
- En el diálogo de la tarea, **Abrir en Asana** lleva a la tarea.

## Cuándo se sincroniza

- Cada 15 minutos WorkDiary lee las tareas abiertas y las terminadas desde la
  última ejecución, y una vez al día todas. Cambie la frecuencia en **Trabajos
  programados**; **Sincronizar ahora** en el menú ⋯ inicia al momento.
- Si una ejecución se interrumpe, queda incompleta y la siguiente continúa
  desde el último estado completo.

## Conflictos y casos por revisar

- Cambios distintos en ambos lados generan un conflicto en la bandeja de
  entrada; no se escribe nada de vuelta en Asana.
- Si una tarea vinculada ya no es visible en Asana, su tarea no cambia y la
  bandeja de entrada informa de un caso por revisar.
- Si la **Soberanía de datos** asigna las tareas a otro sistema, WorkDiary no
  importa nada.
- **Casos por revisar** en el menú ⋯ abre la bandeja de entrada filtrada por
  Asana.

## Pausar, eliminar, desconectar

- **Pausar** y **Reanudar** detienen y reanudan la sincronización de una
  asignación; **Eliminar asignación** conserva las tareas y los tiempos
  importados.
- **Desconectar** revoca la autorización en Asana, elimina el inicio de sesión
  y pone en pausa todas las asignaciones. Conectadas de nuevo con el mismo
  espacio de trabajo, continúan.

## Errores habituales

- «Asana no está configurado para esta instalación»: falta el registro de la
  aplicación; diríjase al operador.
- La conexión informa de un inicio de sesión rechazado: la autorización se
  retiró en Asana. Vuelva a conectar.
- No se crea nada: la asignación aún no está confirmada, está en pausa o las
  tareas ya están terminadas.
