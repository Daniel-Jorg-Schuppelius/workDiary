---
title: "Importación de tareas de Gitea/Forgejo"
topic: admin.gitea
version: 1
keywords:
    - Gitea
    - Forgejo
    - importar incidencias
    - repositorio
    - autoalojado
    - token de acceso
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

La página **Gitea/Forgejo** importa incidencias de repositorios seleccionados de
su instancia autoalojada de Gitea o Forgejo como tareas en sus proyectos de
WorkDiary. Así registra tiempos sobre el trabajo planificado allí. La
importación solo lee: WorkDiary no escribe nada en la instancia. Encontrará la
página en el menú del sistema (el engranaje **Sistema**) en **Plugins** →
**Gitea/Forgejo**, en cuanto el plugin esté activo.

## Configurar el acceso

1. En Gitea o Forgejo, cree en **Configuración** → **Aplicaciones** un token de
   acceso con los ámbitos **read:user**, **read:repository** y **read:issue**.
   Un token limitado a repositorios concretos solo ve esos, también en el
   diálogo.
2. En la página, abra **Credenciales** en el menú ⋯ e introduzca la **URL
   base** (p. ej. https://git.example.com, sin /api/v1) y el **Token de
   acceso**. Si la instancia está en su propia red, active también **Permitir
   direcciones privadas**: solo surte efecto si el operador lo permite.
3. Haga clic en **Comprobar conexión**. WorkDiary inicia sesión en la instancia
   y muestra instancia, dirección y cuenta.

Mientras existan asignaciones, no se puede introducir otra instancia; las tareas
importadas siguen claramente ligadas a su instancia.

## Asignar repositorios y revisar la vista previa

1. **Asignar proyecto** abre un diálogo con los repositorios accesibles para el
   token y los proyectos de WorkDiary. Cada repositorio tiene exactamente un
   destino.
2. La **Vista previa** muestra las incidencias abiertas y lo que haría con ellas
   la primera sincronización. No guarda nada.
3. Vincule tareas existentes del proyecto de destino mediante **Vincular la
   homónima** o **Vincular una tarea existente**; los campos divergentes pasan a
   la bandeja de entrada para su revisión.
4. **Confirmar importación** libera la asignación. Solo entonces la
   sincronización crea las incidencias abiertas como tareas; las cerradas nunca.

## Qué se importa

- **Título**, **descripción** como texto y **vencimiento** como fecha, tal como
  la muestra la instancia.
- **Estado**: cerrada → **Hecho**, abierta → **Abierto**. Si una tarea está **En
  curso** en WorkDiary, sigue así.
- Las pull requests no se importan. Si se cambia el nombre de un repositorio, la
  importación continúa.
- En el diálogo de la tarea, **Abrir en Gitea/Forgejo** lleva a la incidencia.

## Cuándo se sincroniza

Cada 15 minutos WorkDiary obtiene las incidencias modificadas desde la última
ejecución y una vez al día todas. Cambie la frecuencia en **Trabajos
programados**; **Sincronizar ahora** en el menú ⋯ inicia al momento. Una
ejecución interrumpida queda **incompleta**; la siguiente continúa desde el
último estado completo.

## Conflictos y casos por revisar

Cambios distintos en ambos lados generan un conflicto en la bandeja de entrada;
no se escribe nada de vuelta en la instancia. Si una incidencia vinculada ya no
es visible, su tarea no cambia y la bandeja de entrada informa de un caso por
revisar. Si la **Soberanía de datos** asigna las tareas a otro sistema,
WorkDiary no importa nada. **Casos por revisar** en el menú ⋯ abre la bandeja de
entrada filtrada por Gitea/Forgejo.

## Pausar, eliminar, desconectar

**Pausar** y **Reanudar** detienen y reanudan la sincronización de una
asignación; **Eliminar asignación** conserva las tareas y los tiempos
importados. **Desconectar** detiene la sincronización y pone en pausa todas las
asignaciones. Revoque el token en Gitea/Forgejo o elimínelo en
**Credenciales**; una nueva comprobación de la misma instancia reanuda las
asignaciones.

## Errores habituales

- La comprobación informa de una dirección interna: la URL base apunta a su
  propia red, pero **Permitir direcciones privadas** está desactivado o el
  operador no lo permite.
- La comprobación falla con 401: el token es incorrecto, ha caducado o no tiene
  acceso de lectura.
- Falta un repositorio en el diálogo: el token no lo ve, por ejemplo porque
  solo vale para repositorios concretos.
- La comprobación falla con 403: al token le falta uno de los ámbitos
  **read:user**, **read:repository** o **read:issue**.
