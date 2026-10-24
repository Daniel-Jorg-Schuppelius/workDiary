---
title: "Importación de tareas de Jira"
topic: admin.jira
version: 1
keywords:
    - Jira
    - Jira Cloud
    - importar incidencias
    - Atlassian
    - sitio
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

La página **Jira** importa incidencias de proyectos de Jira Cloud
seleccionados como tareas en sus proyectos de WorkDiary. Así registra tiempos
sobre el trabajo planificado en Jira y lo factura como siempre. La importación
solo lee: WorkDiary no escribe nada en Jira. Encontrará la página en el menú
del sistema (el engranaje **Sistema**) en **Plugins** → **Jira**, en cuanto el
plugin esté activo.

## Requisitos

- El plugin está activado para su organización: **Sistema** → **Plugins** →
  **Plugins** y luego **Activar** en la entrada Jira.
- El módulo Kanban tiene licencia; la página está abierta a los
  administradores.
- El operador de su instalación ha configurado la aplicación de Jira del
  editor. Si falta, la página lo indica; no se necesita ni se prevé una
  aplicación propia ni un token de API.
- Cada organización tiene exactamente una conexión con Jira y un sitio.

## Conectar y elegir el sitio

1. Haga clic en **Conectar con Jira** e inicie sesión en Atlassian. WorkDiary
   solo solicita acceso de lectura a proyectos e incidencias.
2. De vuelta en la página, elija el sitio en **Elegir Sitio** y haga clic en
   **Aplicar**. Solo se ofrecen los sitios que el inicio de sesión puede leer.
3. Mientras existan asignaciones de proyectos, el sitio no se puede cambiar:
   las tareas importadas siguen claramente ligadas a su sitio.

## Asignar proyectos y revisar la vista previa

1. **Asignar proyecto** abre un diálogo: elija el **Proyecto de Jira** y el
   **Proyecto de WorkDiary** al que pertenecen las tareas. Cada proyecto de
   Jira tiene exactamente un destino.
2. A continuación se abre la **Vista previa**. Muestra las incidencias
   abiertas y lo que haría con ellas la primera sincronización: **Se creará**,
   **Ya vinculada**, **Otro destino**, **Caso por revisar** o **Gestionada en
   otro sistema**. La vista previa no guarda nada.
3. Si el proyecto de destino ya tiene una tarea adecuada, vincúlela en lugar
   de crear una nueva: con el mismo título mediante **Vincular la homónima**,
   si no, en la sección **Vincular una tarea existente**. Los campos
   divergentes pasan a la bandeja de entrada para su revisión; los campos
   vacíos se rellenan desde Jira.
4. **Confirmar importación** libera la asignación. Solo entonces la
   sincronización crea las incidencias abiertas como tareas. Las incidencias
   ya terminadas nunca se crean.

## Qué se importa

- **Título** a partir del resumen, **descripción** como texto plano (imágenes,
  tablas y otros contenidos que no se pueden mostrar como texto generan un
  caso por revisar; una descripción existente no cambia en ese caso) y
  **vencimiento** como fecha.
- **Estado** según la categoría de estado de Jira: por hacer → **Abierto**, en
  curso → **En curso**, terminado → **Hecho**. Los nombres de los estados de
  su Jira no influyen.
- Cliente, tiempos, tarifas, presupuestos y responsables los sigue gestionando
  WorkDiary.
- Jira no reabre una tarea que usted terminó en WorkDiary. Solo un cierre que
  vino de Jira se reabre con Jira.
- En el diálogo de la tarea, **Abrir en Jira** lleva a la incidencia.

## Cuándo se sincroniza

- Cada 15 minutos WorkDiary obtiene las incidencias modificadas desde la última
  ejecución y una vez al día todas las incidencias de los proyectos asignados.
  Cambie la frecuencia en **Trabajos programados**.
- **Sincronizar ahora** en el menú ⋯ inicia la misma sincronización al
  momento.
- Si una ejecución se interrumpe (por ejemplo, por una incidencia de Jira),
  queda incompleta: la asignación muestra **incompleta** y la siguiente
  ejecución continúa desde el último estado completo.

## Conflictos y casos por revisar

- Si un campo se modificó de forma distinta en WorkDiary y en Jira, aparece un
  conflicto en la bandeja de entrada, donde usted decide qué versión vale. No
  se escribe nada de vuelta en Jira.
- Aparecen casos por revisar, entre otros, cuando una incidencia ya no es
  visible en Jira (su tarea no cambia, no se elimina nada), cuando su tarea
  está en otro proyecto de WorkDiary (no se mueve nada) o cuando llega un
  estado desconocido.
- Si la **Soberanía de datos** asigna las tareas a otro sistema, WorkDiary no
  importa nada y crea un caso por incidencia.
- **Casos por revisar** en el menú ⋯ abre la bandeja de entrada filtrada por
  Jira.

## Pausar, eliminar, desconectar

- **Pausar** y **Reanudar** en la fila de una asignación detienen y reanudan
  su sincronización. **Eliminar asignación** disuelve la asignación; las
  tareas, vínculos y tiempos importados se conservan.
- **Desconectar** elimina el inicio de sesión y pone en pausa todas las
  asignaciones. Si más tarde vuelve a conectar con el mismo sitio, continúan.

## Errores habituales

- «Jira no está configurado para esta instalación»: falta el registro de la
  aplicación; diríjase al operador.
- No hay sitio para elegir: la cuenta conectada no tiene acceso de lectura a
  Jira en ningún sitio.
- La conexión informa de un inicio de sesión rechazado: la autorización se
  revocó en Atlassian o caducó. Vuelva a conectar.
- No se crea nada: la asignación aún no está confirmada, está en pausa o las
  incidencias ya están terminadas.
