---
title: "Importación de Clockify"
topic: admin.clockify
version: 1
keywords:
    - Clockify
    - importar tiempos
    - informe detallado
    - Clockify CSV
    - API de Clockify
    - migrar desde Clockify
    - transferir tiempos
    - asistencia remota a Clockify
    - asignación de usuarios
    - devolver correcciones
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - admin.kimai
    - admin.toggl
    - admin.scheduler
    - admin.organization-settings
---

La página **Importación de Clockify** trae a WorkDiary los registros de tiempo
de Clockify, ya sea como informe detallado (CSV) subido o directamente a
través de la API de Clockify. Si lo desea, también transfiere a Clockify los
tiempos registrados en WorkDiary, por ejemplo sesiones de asistencia remota, y
devuelve las correcciones de los tiempos importados. Encontrará la página en
el menú del sistema (el engranaje **Sistema** en la cabecera) en **Plugins** →
**Importación de Clockify**, en cuanto el plugin esté activo.

## Requisitos

- El plugin está activado para su organización: **Sistema** → **Plugins** →
  **Plugins** y luego **Activar** en la entrada Clockify. La activación y los
  ajustes solo valen para la organización actual.
- La página y los ajustes están reservados a los administradores. La
  **Bandeja de conciliación**, donde resuelve los casos abiertos, también está
  abierta a contabilidad.
- Para la vía CSV basta con un informe detallado de Clockify.
- Para la vía API necesita una clave de API (en Clockify en Profile →
  Advanced → API). El plan gratuito de Clockify solo permite 30 solicitudes de
  API por hora; allí se recomienda la vía CSV.

## Configuración

Las credenciales se guardan en la página **Plugins** mediante **Configurar**
en la entrada Clockify:

1. **Clave de API de Clockify**: la clave de Clockify. Se guarda cifrada; un
   campo vacío conserva el valor anterior al guardar.
2. **ID de espacio de trabajo**: opcional. Si está vacío, WorkDiary usa el
   espacio de trabajo predeterminado de la clave de API.
3. **URL base de la API** y **URL base de la API de Reports**: modifíquelas
   solo si su cuenta está en una instancia regional de Clockify; el texto de
   ayuda del diálogo da un ejemplo.
4. **Franja de sincronización (días)**: hasta dónde mira hacia atrás una
   importación API sin período y hasta dónde llega la transferencia horaria
   (30 días por defecto).
5. **Adoptar como facturable**: activado, adopta la marca de facturable de
   Clockify; desactivado, nunca marca como facturables los tiempos
   importados.
6. **Modo de usuario único** e **Imputar tiempos para el ID de usuario**: solo
   para puestos individuales, véase más abajo.
7. Opcionalmente **Activar la transferencia de tiempos** y **Devolver las
   correcciones**.
8. **Guardar**. Con **Probar conexión** en el diálogo comprueba el acceso.
   Sin clave de API, el plugin indica el modo CSV; no es un error.

## Importar tiempos

**Subir CSV:** en Clockify, exporte el informe detallado como CSV (Clockify →
Reports → Detailed → Export → CSV), seleccione el archivo en la página
**Importación de Clockify** y haga clic en **Importar**. WorkDiary reconoce las
columnas por la fila de encabezado: Project, Client, Description, Task, Email,
Tags, Billable, Start Date, Start Time, End Date, End Time y Duration (h) o
Duration (decimal). Las columnas innecesarias pueden faltar; son obligatorias
Start Date y una hora de fin o una duración. Sirven la coma y el punto y coma,
y el archivo puede tener como máximo 20 MB.

**Importar directamente desde la API de Clockify:** elija opcionalmente un
período (**Desde**, **Hasta**) y haga clic en **Importar desde la API**.
WorkDiary obtiene los registros de todos los usuarios del espacio de trabajo;
sin período, los últimos días según la franja de sincronización. La
importación omite los registros en curso, sin fin.

No existe una importación programada de Clockify: cada importación empieza en
esta página. Después la página indica cuántas entradas se crearon, se
omitieron o quedaron abiertas en la bandeja, y cuántas no pudieron asignarse
a ningún usuario.

## Asignar clientes, proyectos y personas

- **Proyectos:** la importación no crea clientes ni proyectos. Una entrada se
  contabiliza cuando se encuentra su proyecto: mediante una asignación
  memorizada o, si no, mediante el mismo nombre de proyecto en el cliente
  correspondiente. Si en los ajustes de la organización está activado
  **Asignar tiempos a proyectos por palabra clave**, una coincidencia
  inequívoca por palabra clave ayuda como último paso.
- **Bandeja de conciliación:** todo lo demás se acumula allí, agrupado por
  cliente, proyecto y tarea de Clockify. La tarjeta **Bandeja de
  conciliación** de la página muestra el número de grupos abiertos y **A la
  bandeja de entrada** lleva hasta ella. Allí elige el cliente, opcionalmente
  el cliente final, y el proyecto, y contabiliza el grupo. La asignación se
  memoriza; las importaciones siguientes contabilizan entonces sin preguntar.
- **Personas:** cada tiempo pertenece a la persona que lo registró en
  Clockify. WorkDiary compara su dirección de correo (columna Email o dato de
  la API) con la dirección de correo de los usuarios activos. Sin
  coincidencia se crea en la bandeja un caso «Usuario desconocido» o «Entrada
  sin señal de usuario», en lugar de que el tiempo acabe sin aviso en el
  usuario principal. Elija allí el usuario; la elección se memoriza.
- **Modo de usuario único:** solo si está activado, la importación asigna las
  entradas sin persona identificable al usuario predeterminado. Es el usuario
  de **Imputar tiempos para el ID de usuario** o, si no, el propietario de la
  organización o el primer usuario.

## Nueva importación y cambios

- Una nueva importación nunca crea dos veces entradas ya importadas.
- En la importación API, WorkDiary reconoce cada entrada por su identificador
  de Clockify. Si una entrada conocida cambió en Clockify (inicio, fin,
  duración, descripción), WorkDiary adopta el cambio. Si el tiempo ya está
  facturado o exportado aquí, WorkDiary no cambia nada; el caso aparece en la
  bandeja a título informativo.
- Si una importación API ya no encuentra, en el período consultado, una
  entrada importada o transferida anteriormente, esta se considera eliminada
  en Clockify. WorkDiary elimina entonces también el tiempo, salvo que esté
  facturado; en ese caso se crea un caso en la bandeja.
- En la vía CSV, WorkDiary reconoce una entrada por su hora, cliente,
  proyecto, tarea, descripción y correo. Si se modificó en Clockify, volver a
  subirla crea una entrada adicional. Las importaciones CSV no provocan
  eliminaciones.
- Las etiquetas de Clockify se añaden, nunca se quitan.

## Transferir tiempos a Clockify

Con **Activar la transferencia de tiempos**, WorkDiary refleja en Clockify los
tiempos de trabajo registrados en WorkDiary con inicio y fin:

- Solo se transfieren los tiempos de proyectos vinculados a un proyecto de
  Clockify. Un proyecto cuenta como vinculado en cuanto haya contabilizado en
  él un grupo de Clockify en la Bandeja de conciliación y exista en Clockify
  un proyecto con el mismo cliente y el mismo nombre. Los proyectos que la
  importación encontró solo por el nombre no cuentan.
- Los registros se crean siempre para el titular de la clave de API; Clockify
  no permite otra cosa.
- Los tiempos nuevos salen justo después de registrarse. Además, una
  ejecución horaria recupera lo que aún falte dentro de la franja de
  sincronización. Con **Transferir a Clockify** en la sección **Transferir
  tiempos a Clockify** inicia la transferencia a mano, opcionalmente para un
  período.
- A diferencia de una reescritura, el tiempo sigue siendo facturable en
  WorkDiary. Después se comporta como un tiempo importado: los cambios y las
  eliminaciones se sincronizan en ambas direcciones.
- Se omiten los tiempos ya transferidos y los importados de Clockify.

## Devolver las correcciones

Con **Devolver las correcciones**, WorkDiary transmite a Clockify los cambios
de los tiempos importados o transferidos por la API (descripción, inicio, fin,
duración, facturable) y su eliminación. Antes, WorkDiary compara el estado
actual en Clockify: si la entrada se modificó allí mientras tanto, WorkDiary
no sobrescribe nada y crea en su lugar un conflicto en la bandeja. Los tiempos
facturados y los importados por CSV nunca se devuelven.

## Errores frecuentes

- **No hay clave API registrada** en lugar de la sección de importación:
  falta la clave de API en los ajustes del plugin.
- El mensaje menciona el plan Free con 30 solicitudes por hora: la cuota está
  agotada. Importe por CSV o espere; la siguiente ejecución continúa una
  transferencia interrumpida.
- «Clockify: no se puede determinar el workspace»: introduzca el **ID de
  espacio de trabajo**.
- «Ningún proyecto está asignado a un proyecto de Clockify»: contabilice
  primero grupos de Clockify en los proyectos deseados en la bandeja.
- Muchos casos «Usuario desconocido»: las direcciones de correo de Clockify
  difieren de las de WorkDiary. Asigne cada persona una vez en la bandeja.
- Fechas erróneas en la importación CSV: las fechas en formato con barra en
  las que día y mes son ambos 12 o menos se leen como mes/día. Configure en
  Clockify un formato de fecha inequívoco.
- Si los errores se acumulan, WorkDiary desactiva el plugin automáticamente;
  una vez corregida la causa, restablézcalo en la página **Plugins** con
  **Restablecer y reactivar**.
