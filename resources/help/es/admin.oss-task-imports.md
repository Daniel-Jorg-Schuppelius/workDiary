---
title: "Importaciones de tareas desde herramientas de código abierto"
topic: admin.oss-task-imports
version: 1
keywords:
    - Vikunja
    - Kanboard
    - Plane
    - Taiga
    - Leantime
    - FreeScout
    - GLPI
    - importación de tareas
    - importar tickets
    - autoalojado
    - token de API
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

Siete plugins incorporan elementos de herramientas de código abierto muy
extendidas como tareas en proyectos de WorkDiary: **Vikunja**, **Kanboard**,
**Plane**, **Taiga** y **Leantime** para la planificación de proyectos,
**FreeScout** y **GLPI** para soporte y tickets. Así registra tiempo sobre el
trabajo que se planifica o se solicita allí. Cada importación solo lee: WorkDiary
no escribe nada en el sistema de origen. Encontrará las páginas en el menú del
sistema (el engranaje **Sistema**) en **Plugins**, en cuanto el plugin
correspondiente esté activo. Los siete funcionan igual; este texto indica las
diferencias de cada sistema.

## Configurar el acceso

1. Cree en el sistema de origen un acceso con permiso de lectura (vea la tabla).
2. En la página del plugin, abra **Credenciales** en el menú ⋯ e introduzca la
   **dirección** y el acceso. Si la instancia está en su propia red, active
   también **Permitir direcciones privadas**; solo surte efecto si el operador
   lo permite.
3. Haga clic en **Comprobar conexión**. WorkDiary inicia sesión y muestra la
   instancia, la dirección y, si el sistema la indica, la cuenta.

| Sistema | Dirección | Acceso |
| --- | --- | --- |
| Vikunja | Dirección de la instancia | Token de API de Ajustes → Tokens de API |
| Kanboard | Dirección de la instancia | Token de aplicación de Ajustes → API (nombre de usuario vacío) o nombre de usuario y token de acceso personal |
| Plane | https://api.plane.so o su propia instancia | Identificador del espacio de trabajo y token de acceso personal |
| Taiga | https://api.taiga.io o su propia instancia | Nombre de usuario y contraseña |
| Leantime | Dirección de la instancia | Clave de API de los ajustes de la empresa |
| FreeScout | Dirección de la instancia | Clave de API del módulo «API & Webhooks» |
| GLPI | Dirección de la instancia | Token de usuario («clave de acceso remoto»), token de aplicación si hace falta |

Mientras existan asignaciones, no se puede introducir otra instancia; así las
tareas importadas siguen claramente ligadas a su instancia. En Plane, el
espacio de trabajo cuenta como instancia.

## Requisitos por sistema

- **Vikunja** a partir de la versión 1.0. El token de API necesita permisos de
  lectura sobre proyectos y tareas; con el permiso «other → user», la
  comprobación muestra también el nombre de la cuenta.
- **Kanboard** solo ofrece proyectos activos; los inactivos no aparecen.
- **Plane** a partir de la versión 0.28. Solo se ofrecen los proyectos en los
  que la cuenta del token es miembro. Plane permite 60 solicitudes por minuto;
  por eso la primera sincronización de proyectos grandes tarda unos minutos.
- **Taiga**: la cuenta necesita en el proyecto el permiso para ver historias de
  usuario. Sin él, Taiga devuelve una lista vacía; WorkDiary marca entonces la
  ejecución como incompleta en lugar de dar tareas por eliminadas.
- **Leantime** a partir de la versión 3.8.
- **FreeScout**: solo se incorporan conversaciones publicadas, ni borradores ni
  eliminadas.
- **GLPI**: la API REST debe estar activada y la cuenta necesita permisos de
  lectura sobre los tickets de la entidad. WorkDiary cierra la sesión después de
  cada ejecución.

## Asignar orígenes y revisar la vista previa

El origen es un proyecto en cada sistema, un **buzón** en FreeScout y una
**entidad** en GLPI (sin subentidades). En el diálogo, los buzones y las
entidades aparecen en la lista de proyectos.

1. **Asignar proyecto** abre un diálogo con los orígenes que ve el acceso y los
   proyectos de WorkDiary. Cada origen tiene exactamente un destino.
2. La **Vista previa** muestra los elementos abiertos y lo que haría con ellos
   la primera sincronización. No guarda nada.
3. Vincule tareas existentes del proyecto de destino mediante **Vincular la
   homónima** o **Vincular una tarea existente**; los campos divergentes pasan a
   la bandeja de entrada para su revisión.
4. **Confirmar importación** habilita la asignación. Solo entonces la
   sincronización crea los elementos abiertos como tareas; los ya terminados,
   nunca.

## Qué se incorpora

Se incorporan el **título**, la **descripción** como texto y, si existe, el
**vencimiento**. El estado siempre procede de un campo fijo del sistema, nunca
de nombres de columnas o estados que se pueden nombrar libremente:

| Sistema | Abierto | En curso | Hecho | Vencimiento |
| --- | --- | --- | --- | --- |
| Vikunja | no hecha | – | hecha | fecha de vencimiento |
| Kanboard | activa | – | cerrada | fecha de vencimiento |
| Plane | grupo Backlog, Unstarted, Triage | grupo Started | grupo Completed, Cancelled | fecha objetivo |
| Taiga | abierta | – | cerrada | fecha de vencimiento |
| Leantime | tipo de estado «Nuevo» | tipo de estado «En curso» | tipo de estado «Hecho» | fecha de vencimiento |
| FreeScout | activa | pendiente | cerrada, spam | – |
| GLPI | nuevo, aprobación | asignado, planificado, en espera | resuelto, cerrado | plazo de resolución |

- Si una tarea está **En curso** en WorkDiary, sigue así mientras el origen no
  la cierre.
- En FreeScout, la descripción es el primer mensaje de la conversación; las
  notas internas quedan fuera. En Taiga, WorkDiary solo lee la descripción de
  historias de usuario nuevas o modificadas.
- Si Taiga cierra una historia de usuario porque todas sus tareas están
  terminadas, esto llega con la siguiente sincronización. Si una tarea nueva la
  reabre, WorkDiary solo lo ve en la sincronización diaria de todos los
  elementos.
- Los hitos de Leantime no se incorporan.
- En el diálogo de la tarea, **Abrir en …** lleva al original.

## Cuándo se sincroniza

Cada 15 minutos WorkDiary obtiene los elementos modificados desde la última
ejecución y una vez al día todos. Cambie la frecuencia en **Trabajos
programados**; **Sincronizar ahora** en el menú ⋯ inicia al momento. Una
ejecución interrumpida queda **incompleta**; la siguiente continúa desde el
último estado completo.

## Conflictos y casos por revisar

Cambios distintos en ambos lados generan un conflicto en la bandeja de entrada;
no se escribe nada de vuelta en el sistema de origen. Si un elemento vinculado
ya no es visible, su tarea queda sin cambios y la bandeja de entrada informa de
un caso por revisar. Si, según la **Soberanía de datos**, otro sistema lleva las
tareas, WorkDiary no incorpora nada. **Casos por revisar** en el menú ⋯ abre la
bandeja de entrada filtrada por el plugin.

## Pausar, eliminar, desconectar

**Pausar** y **Reanudar** detienen y reinician la sincronización de una
asignación; **Eliminar asignación** conserva las tareas y los tiempos
importados. **Desconectar** termina la sincronización y pausa todas las
asignaciones. Retire el acceso en el sistema de origen o elimínelo en
**Credenciales**; una nueva comprobación con la misma instancia reanuda las
asignaciones.

## Errores típicos

- La comprobación indica una dirección interna: la dirección apunta a su propia
  red, pero **Permitir direcciones privadas** está desactivado o el operador no
  lo permite.
- La comprobación falla con 401: el token, la clave o la contraseña son
  incorrectos, han caducado o no tienen acceso de lectura.
- La comprobación indica «…VersionUnsupportedException»: la instancia es más
  antigua que la versión mínima indicada arriba.
- Plane no muestra un proyecto: la cuenta del token no es miembro allí, aunque
  el proyecto sea público.
- Kanboard muestra solo una parte de los proyectos: con un nombre de usuario, el
  acceso solo ve los proyectos de ese usuario; para todos los proyectos use el
  token de aplicación sin nombre de usuario.
- Taiga rechaza el inicio de sesión: las cuentas que inician sesión con GitHub o
  GitLab necesitan su propia contraseña.
- Leantime o GLPI no muestran ningún origen: la clave de API no está asignada a
  ningún proyecto o la API REST no está activada en GLPI.
- FreeScout responde con 404: el módulo «API & Webhooks» no está activo.
