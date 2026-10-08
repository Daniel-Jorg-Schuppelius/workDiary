---
title: "Calendario CalDAV"
topic: admin.caldav
version: 1
keywords:
    - CalDAV
    - calendario de Nextcloud
    - calendario de ownCloud
    - publicar citas
    - suscribirse al calendario
    - turnos en el calendario
    - vacaciones en el calendario
    - sincronización bidireccional
    - contraseña de aplicación
    - ruta del calendario
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - events.manage
    - planning.shifts
    - absences.manage
    - admin.import
    - admin.notification-rules
---

La página **CalDAV** publica las citas de WorkDiary en un calendario CalDAV
externo, por ejemplo en Nextcloud u ownCloud, sin cuenta de Microsoft ni de
Google. Si lo desea, se añaden turnos y vacaciones, y los cambios hechos en el
calendario pueden volver como propuestas. WorkDiary sigue siendo el sistema
de referencia: las citas canceladas desaparecen allí y las ejecuciones
repetidas nunca crean duplicados. Encontrará la página en el menú del sistema
(el engranaje **Sistema** en la cabecera) en **Plugins** → **CalDAV**, en
cuanto el plugin esté activo.

## Requisitos

- El plugin está activado para su organización: **Sistema** → **Plugins** →
  **Plugins** y luego **Activar** en la entrada CalDAV. La conexión en sí se
  configura en la página **CalDAV**, no en el diálogo del plugin.
- La página está reservada a los administradores.
- Necesita un calendario en el servidor CalDAV, una cuenta con permiso de
  escritura en él y una contraseña de aplicación (Nextcloud: Ajustes →
  Seguridad → Contraseña de aplicación).
- El servidor debe ser accesible públicamente. WorkDiary rechaza las
  direcciones de una red interna.
- Por organización existe exactamente una conexión CalDAV.

## Configurar la conexión

En la sección **Conexión** rellena:

- **Etiqueta**: un nombre a su elección; también aparece al elegir la fuente
  de importación.
- **URL base DAV**: la dirección DAV del servidor sin ruta del calendario, en
  Nextcloud …/remote.php/dav. Debe empezar por http:// o https://.
- **Nombre de usuario** y **Contraseña de aplicación**: la contraseña es
  obligatoria al guardar por primera vez y se guarda cifrada; más adelante,
  un campo vacío conserva la contraseña guardada.
- **Ruta del calendario (colección)**: la ruta del calendario relativa a la
  URL base, por ejemplo calendars/team/turnos. Si pega una dirección completa
  copiada desde Nextcloud, WorkDiary la acorta por sí mismo, siempre que
  empiece por la URL base.
- **Activo**: activa o desactiva la conexión.
- **Bidireccional: importar los cambios externos como propuestas**: véase
  más abajo.
- **Contenido publicado**: **Eventos** y/o **Turnos y vacaciones**. Sin
  selección, solo se publican los eventos.

**Guardar** aplica los datos. Cuando la conexión está activa, la página
muestra su estado (por ejemplo **Estado correcto**) y **Probar conexión**.

## Qué se publica

- **Eventos:** los eventos de su organización que empiezan entre 30 días en el
  pasado y 180 días en el futuro. WorkDiary retira del calendario los eventos
  cancelados.
- **Turnos y vacaciones:** los turnos publicados o confirmados con horario a
  partir de dos meses en el pasado, y las vacaciones aprobadas que terminaron
  hace como máximo un año. Los turnos en borrador, sin horario o cancelados y
  las vacaciones que ya no están aprobadas se retiran o ni siquiera se crean.
- Las reglas de notificación con el canal **Calendario** también depositan
  notificaciones de tipo cita en las conexiones con **Eventos**.
- Las entradas modificadas se actualizan; las que no cambian no se vuelven a
  enviar.

## Cuándo se publica

- Una vez al día (por defecto a las 04:35), WorkDiary sincroniza el
  calendario. La frecuencia se cambia en **Tareas programadas**.
- **Publicar ahora** inicia la sincronización de inmediato en segundo plano,
  por ejemplo tras la configuración o tras muchos cambios.
- Por tanto, las citas nuevas o modificadas solo aparecen en el calendario
  tras la siguiente ejecución. Solo las notificaciones por el canal
  **Calendario** salen de inmediato.

## Bidireccional: cambios desde el calendario

La reimportación sigue desactivada hasta que active **Bidireccional:
importar los cambios externos como propuestas**. Entonces WorkDiary lee el
calendario cada hora, en una ventana desde 30 días atrás hasta 180 días
adelante:

- Las entradas nuevas del calendario se convierten en propuestas en la
  Bandeja de conciliación. No se crea nada sin preguntar.
- Las citas periódicas aparecen como grupo con sus repeticiones dentro de la
  ventana; WorkDiary tiene en cuenta las repeticiones movidas y canceladas.
  El grupo puede crearse como citas o descartarse de una vez.
- Los cambios externos en citas publicadas aparecen como conflicto, y las
  entradas eliminadas en el calendario como caso «Cita eliminada en el
  calendario CalDAV». WorkDiary no elimina nada por sí mismo en ese caso.

La Bandeja de conciliación está abierta a los administradores y a
contabilidad.

## El calendario como fuente de importación

En la importación CSV (**Transferencia de datos** → **Importación**) puede
elegir el calendario CalDAV en lugar de un archivo como fuente para fichajes y
tiempos de proyecto. Se ofrecen las conexiones activas con su **Etiqueta**;
WorkDiary lee entonces las entradas del período elegido.

## Desconectar

**Desconectar** desactiva la conexión. Las entradas ya publicadas se quedan
en el calendario. Para volver a activarla, marque **Activo** y guarde.

WorkDiary tampoco retira del calendario los eventos eliminados ni las
entradas que salen de la ventana de tiempo. Si una cita debe desaparecer de
allí, cancélela en lugar de eliminarla.

## Errores frecuentes

- «La URL base debe empezar por http:// o https://.»: introduzca la dirección
  completa.
- «La URL del calendario no está bajo la URL base.»: el enlace pegado no
  coincide con la URL base DAV. Indique la ruta relativa a la URL base.
- «Una conexión nueva requiere una contraseña de aplicación.»: falta la
  contraseña al guardar por primera vez.
- **Estado defectuoso** con «Servidor CalDAV no accesible o credenciales no
  válidas.»: compruebe la dirección, la ruta del calendario, el nombre de
  usuario y la contraseña de aplicación. Un error de CalDAV con
  RuntimeException suele indicar una dirección de una red interna.
- «No hay ninguna conexión CalDAV activa.» con **Publicar ahora**: la conexión
  está desactivada o incompleta.
- Faltan turnos en el calendario: en **Contenido publicado** no está marcado
  **Turnos y vacaciones**, o los turnos aún no están publicados.
