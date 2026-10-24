---
title: "Importación de tareas y tiempos de Redmine"
topic: admin.redmine
version: 1
keywords:
    - Redmine
    - importar incidencias
    - importar registros de tiempo
    - clave de API
    - autoalojado
    - asignación de usuarios
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

La página **Redmine** importa incidencias de proyectos de Redmine seleccionados
como tareas en proyectos de WorkDiary y, si se desea, los registros de tiempo
anotados allí como tiempos registrados. La importación solo lee: WorkDiary no
escribe nada en Redmine. Encontrará la página en el menú del sistema (el
engranaje **Sistema**) en **Plugins** → **Redmine**, en cuanto el plugin esté
activo.

## Configurar el acceso

1. En Redmine, active el servicio web REST en **Administración** →
   **Configuración** → **API** y copie la **Clave de acceso a la API** en **Mi
   cuenta**.
2. En la página, abra **Credenciales** en el menú ⋯ e introduzca **URL base** y
   **Clave de API**. Para una instancia en su propia red, active también
   **Permitir direcciones privadas**. Allí decide también si los tiempos
   importados son facturables (**Importar tiempos como facturables**) y cuántos
   días hacia atrás lee cada ejecución (**Ventana de tiempo (días)**).
3. Haga clic en **Comprobar conexión**. Mientras existan asignaciones, no se
   puede introducir otra instancia.

La cuenta de la clave necesita en cada proyecto asignado un rol que pueda ver
incidencias y – para la importación de tiempos – el tiempo dedicado. Ajuste la
visibilidad de incidencias y registros de tiempo de ese rol a **todos**; de lo
contrario, WorkDiary solo incorpora lo que la cuenta creó o lo que tiene
asignado.

## Asignar proyectos y revisar la vista previa

1. **Asignar proyecto** elige un proyecto de Redmine y el proyecto de WorkDiary.
   Los subproyectos no se incluyen, ni en incidencias ni en tiempos; asígnelos
   por separado si hace falta.
2. La **Vista previa** muestra las incidencias abiertas y lo que haría la
   primera sincronización; no guarda nada. Vincule tareas existentes mediante
   **Vincular la homónima** o **Vincular una tarea existente**.
3. **Confirmar importación** libera la asignación. Solo entonces se crean tareas
   a partir de las incidencias abiertas; nunca de las cerradas.

## Qué se importa

- **Título**, **descripción** como texto y **vencimiento**.
- **Estado**: una incidencia con estado cerrado (marcado como cerrado en Redmine)
  pasa a **Hecho**; si no, **Abierto**; los nombres de los estados no influyen.
  Un **En curso** local se mantiene.
- En el diálogo de la tarea, **Abrir en Redmine** lleva a la incidencia.

## Registros de tiempo

1. En la sección **Registros de tiempo**, active **Importar tiempos** para cada
   asignación.
2. Redmine solo conoce día y horas. Por eso WorkDiary registra una duración sin
   hora en el proyecto de destino; si el registro pertenece a una incidencia
   vinculada, pertenece a la tarea de esa incidencia.
3. Solo se registran tiempos de usuarios de Redmine asignados. En **Asignación de
   usuarios** aparecen los usuarios de Redmine con registros pendientes; elija el
   usuario correspondiente y haga clic en **Asignar**. La próxima sincronización
   registra los pendientes.
4. Cada ejecución lee la ventana de tiempo completa. Las horas modificadas se
   actualizan y los registros eliminados en Redmine desaparecen también aquí,
   salvo los tiempos ya facturados: en ese caso la bandeja de entrada informa de
   un conflicto y el tiempo no cambia.

## Cuándo se sincroniza

Cada 15 minutos WorkDiary obtiene las incidencias modificadas y los registros de
tiempo de la ventana, y una vez al día todas las incidencias. Cambie la
frecuencia en **Trabajos programados**; **Sincronizar ahora** en el menú ⋯ inicia
al momento.

## Conflictos, casos por revisar y desconexión

Cambios distintos en ambos lados generan un conflicto en la bandeja de entrada;
no se escribe nada de vuelta en Redmine. Las incidencias ya no visibles, los
datos gestionados en otro sistema y los estados desconocidos se informan como
casos por revisar. **Desconectar** detiene la sincronización y pone en pausa las
asignaciones; las tareas y los tiempos importados se conservan. Revoque la clave
en Redmine o elimínela en **Credenciales**.

## Errores habituales

- La comprobación falla con 401 o 403: la clave de API es incorrecta o el
  servicio web REST está desactivado en Redmine.
- No aparecen tiempos: **Importar tiempos** está desactivado, el usuario de
  Redmine aún no está asignado o el registro está fuera de la ventana de tiempo.
- La sincronización se detiene con 401: Redmine trata una clave no válida como
  un visitante sin iniciar sesión y entonces solo muestra datos públicos. Por
  eso WorkDiary comprueba la cuenta antes de cada ejecución y no lee nada con
  una clave no válida.
