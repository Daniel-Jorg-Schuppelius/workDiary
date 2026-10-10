---
title: "Conectar Google Calendar"
topic: admin.google-calendar
version: 2
keywords:
    - Google Calendar
    - calendario de Google
    - citas a Google
    - sincronizar calendario
    - Google Workspace
    - sincronización de calendario
    - calendario bidireccional
    - exportar citas
    - publicar calendario
    - Google Cloud Console
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.msgraph
    - events.manage
    - admin.integration-inbox
    - admin.notification-rules
    - admin.import
    - admin.scheduler
---

La página **Google Calendar** transfiere los eventos de WorkDiary a un
calendario de una cuenta de Google. WorkDiary sigue siendo el sistema de
referencia: los cambios se trasladan, los eventos cancelados y eliminados desaparecen del
calendario de Google y las ejecuciones repetidas no crean duplicados. Si lo
desea, WorkDiary vuelve a leer además el calendario y le presenta los cambios
externos como propuestas para revisar.

## Requisitos previos

- El plugin **Google Calendar** está activado en **Plugins**. A continuación
  aparece la entrada **Google Calendar** en el menú del sistema (icono de
  engranaje **Sistema**), dentro del grupo **Plugins**.
- Existe un cliente OAuth en Google Cloud Console. O bien el operador ha
  guardado uno para toda la instalación, o bien su organización usa uno propio:
  en **Plugins**, abra el diálogo **Configurar** de **Google Calendar** e
  introduzca **ID de cliente (app propia de Google Cloud)** y **Secreto de
  cliente**. Un cliente propio debe conocer su dirección de WorkDiary con la
  ruta /admin/google-calendar/oauth/callback como URI de redirección
  autorizada.
- Google clasifica el acceso al calendario como sensible. Por eso la
  aplicación necesita una verificación de Google, o bien usted configura la
  pantalla de consentimiento con el tipo «Interno» en Google Workspace.
- Si no hay cliente OAuth, la página muestra un aviso en lugar del botón de
  conexión.
- Necesita una cuenta de Google con permiso de escritura en el calendario de
  destino. La conexión puede editar citas y leer la lista de calendarios.
- La página está reservada a los administradores de su organización. Cada
  organización tiene una conexión.

## Conectar

1. Haga clic en **Conectar con Google**. Se abre el inicio de sesión de Google;
   inicie sesión y permita el acceso.
2. Google le devuelve a la página. El mensaje «Cuenta de Google conectada.»
   confirma la conexión; junto al título aparece la insignia **Conectado**.

El proceso debe terminarlo la misma persona que lo inició, en la misma sesión.

## Elegir el calendario de destino

En la sección **Calendario de destino**, elija en **Calendario** uno de los
calendarios de la cuenta conectada. Sin elección se aplica el **Calendario
principal**. Allí también activa, si lo necesita, **Bidireccional: importar los
cambios externos como propuestas**. Después haga clic en **Guardar**. Si cambia
de calendario, la importación empieza desde cero.

## Qué se transfiere y cuándo

- **Contenido:** los eventos desde 30 días atrás hasta 180 días adelante, con
  título, descripción, horario y lugar (salas reservadas). Los eventos
  cancelados se eliminan del calendario de Google.
- **Momento:** cada día se ejecuta una sincronización, por defecto a las 4:55;
  la frecuencia se cambia en **Tareas programadas**. **Publicar ahora** la
  inicia de inmediato en segundo plano.
- **Notificaciones:** las notificaciones con fecha de vencimiento salen al
  momento como entrada de calendario si una regla de notificación usa el canal
  **Calendario**.
- **Sin modo bidireccional**, WorkDiary no lee ninguna cita del calendario de
  Google.

## Importación bidireccional

Con el modo bidireccional activado, WorkDiary vuelve a leer el calendario de
destino cada hora. De ello solo resultan entradas en la **Bandeja de
conciliación**, nunca citas creadas por su cuenta:

- Una cita nueva que no procede de WorkDiary se convierte en una propuesta.
- Un evento transferido que se modificó en Google se convierte en un
  conflicto; de lo contrario, la siguiente sincronización sobrescribiría el
  cambio en silencio.
- Un evento transferido que se eliminó o canceló en Google aparece como «Cita
  eliminada en Google Calendar».
- Las series aparecen como citas individuales dentro del periodo de
  importación y pueden aceptarse o descartarse en grupo.

La **Bandeja de conciliación** está abierta a las personas autorizadas a
gestionar la facturación. Con independencia del modo bidireccional, la
importación de fichajes y tiempos de proyecto ofrece el calendario conectado
como fuente.

## Desconectar y volver a conectar

**Desconectar** retira el acceso. Las citas ya transferidas permanecen en el
calendario de Google. Con **Conectar con Google** restablece la conexión en
cualquier momento; el calendario elegido sigue guardado y el contador de
errores vuelve a empezar. Mientras la conexión falle, una tarea operativa lo
indica.

## Problemas habituales

- **Sin botón de conexión:** no hay ningún cliente OAuth guardado (véanse los
  requisitos previos).
- **«El flujo OAuth ha caducado o no es válido. Inténtelo de nuevo.»** El
  inicio de sesión tardó demasiado o se completó en otra sesión. Inicie de
  nuevo la conexión.
- **«La conexión fue rechazada o cancelada.»** Se denegó el consentimiento, o
  Google no admite la aplicación para esta cuenta, por ejemplo porque todavía
  no está verificada. Revise la pantalla de consentimiento en Google Cloud
  Console.
- **Insignia Inaccesible:** la API de Google Calendar no es accesible o deniega
  el acceso, por ejemplo tras una revocación en la cuenta de Google.
  **Desconectar** y volver a conectar.
- **«El calendario seleccionado no se ha encontrado.»** El calendario se
  eliminó o la cuenta perdió el acceso. Elija otro.
- **Suspensión:** tras errores repetidos consecutivos, WorkDiary suspende la
  conexión; entonces vuelve a aparecer **Conectar con Google**. Compruebe la
  causa y vuelva a conectar.
