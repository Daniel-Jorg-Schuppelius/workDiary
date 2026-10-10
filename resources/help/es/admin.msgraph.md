---
title: "Conectar Microsoft 365"
topic: admin.msgraph
version: 2
keywords:
    - Microsoft 365
    - Office 365
    - calendario de Outlook
    - enviar correo con Microsoft
    - contactos de Outlook
    - Microsoft To Do
    - OneNote
    - reunión de Teams
    - consentimiento de administrador
    - Entra ID
    - respuesta automática
    - Exchange Online
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.sharepoint
    - admin.google-calendar
    - events.manage
    - admin.integration-inbox
    - admin.notification-rules
    - knowledge.collections
    - cloud-intake.overview
    - backup-targets.overview
---

La página **Microsoft 365** reúne las conexiones con Microsoft 365 a través de
Microsoft Graph: calendario, envío de correo, contactos a Outlook, Microsoft To
Do y la importación desde OneNote, además de la concesión a nivel de tenant.
Cada función tiene su propia conexión, con su propio inicio de sesión y solo
los permisos que necesita: conecta únicamente lo que de verdad utiliza. Cada
conexión vale para toda la organización y trabaja con la cuenta de Microsoft
que da su consentimiento al iniciar sesión.

## Requisitos previos

- El plugin **Microsoft 365** está activado en **Plugins**. A continuación
  aparece la entrada **Microsoft 365** en el menú del sistema (icono de
  engranaje **Sistema**), dentro del grupo **Plugins**.
- Existe un registro de aplicación en Microsoft Entra ID. O bien el operador ha
  guardado una aplicación para toda la instalación, o bien su organización usa
  una propia: en **Plugins**, abra el diálogo **Configurar** de **Microsoft
  365** e introduzca **ID de cliente (registro de aplicación propio)**,
  **Secreto de cliente** y **Tenant (ID de directorio)**. El tenant es el GUID
  de su directorio o uno de los valores common, organizations o consumers; si
  queda vacío, se aplica el valor de la aplicación de la instalación.
- Si no hay aplicación, la página muestra un aviso y faltan los botones de
  conexión.
- Necesita una cuenta de Microsoft autorizada a dar su consentimiento a los
  permisos. La página está reservada a los administradores de su
  organización.

## Calendario

En la parte superior de la página conecta el calendario con **Conectar con
Microsoft 365**. Después dispone allí de **Publicar ahora** y **Desconectar**;
junto al título, una insignia muestra **Conectado**, **Inaccesible** o
**Inactivo**.

- **Dirección:** los eventos de WorkDiary se transfieren al calendario de la
  cuenta conectada, desde 30 días atrás hasta 180 días adelante, con título,
  descripción, horario y lugar (salas reservadas). Los cambios se trasladan,
  los eventos cancelados y eliminados se retiran allí y las ejecuciones repetidas no crean
  duplicados. WorkDiary sigue siendo el sistema de referencia.
- **Momento:** cada día se ejecuta una sincronización, por defecto a las 4:45;
  la frecuencia se cambia en **Tareas programadas**. **Publicar ahora** la
  inicia de inmediato en segundo plano. Además, las notificaciones con fecha de
  vencimiento salen al momento como entrada de calendario si una regla de
  notificación usa el canal **Calendario**.
- **Calendario de destino:** con una conexión activa, elija en **Calendario**,
  dentro de la sección del mismo nombre, un calendario de la cuenta; sin
  elección se aplica el **Calendario predeterminado**. Después haga clic en
  **Guardar**.
- **Crear los eventos nuevos como reuniones de Teams (enlace de acceso):** los
  eventos transferidos a partir de entonces reciben un enlace de acceso de
  Teams. La opción no modifica los eventos ya transferidos.
- **Bidireccional: importar cambios externos como propuestas:** el calendario
  de destino se vuelve a leer cada hora y, además, Microsoft avisa de los
  cambios al momento. Las citas externas nuevas se convierten en propuestas,
  los cambios en eventos transferidos en conflictos, y las citas eliminadas
  aparecen como «Cita eliminada en Microsoft 365», todo en la **Bandeja de
  conciliación**, nunca como cita creada a ciegas. Las series aparecen como
  citas individuales y allí pueden aceptarse o descartarse en grupo. Si cambia
  el calendario de destino, la importación empieza desde cero.

La conexión del calendario la usan además:

- **Comprobar disponibilidad (Microsoft 365)** en el diálogo de un evento:
  muestra libre u ocupado para los participantes elegidos, sin detalles de las
  citas.
- la importación de fichajes y tiempos de proyecto, que ofrece el calendario
  conectado como fuente.
- el recuadro **Equipo (estado de Teams)** de la página **Reloj de fichar**.
  Solo aparece si la instalación ha habilitado el acceso de lectura al estado
  de Teams y la conexión del calendario se ha vuelto a establecer después.

## Envío de correo a través de Microsoft 365

Con **Conectar el envío de correo**, una cuenta permite a WorkDiary enviar
correos en su nombre, por ejemplo facturas, recordatorios de pago y
notificaciones, sin acceso SMTP. Después ve la **Cuenta conectada** y ajusta:

- **Dirección del remitente (opcional)**: si queda vacía, la cuenta envía en
  su propio nombre. Otra dirección, por ejemplo un buzón compartido, requiere
  en Exchange el permiso «Enviar como» (Send As) y un permiso adicional que el
  operador habilita para la aplicación.
- **Guardar una copia en la carpeta Elementos enviados**.
- **Guardar**.

**Enviar correo de prueba** envía al instante un mensaje por esta conexión, al
**Destinatario (opcional)** o, si queda vacío, a la cuenta conectada. La prueba
usa la misma dirección de remitente que el envío real, de modo que la falta de
permisos de envío se nota enseguida. Si WorkDiary envía realmente sus correos
por esta conexión lo decide el operador de la instalación; si no está
configurado, la tarjeta muestra un aviso. **Desconectar el envío de correo**
retira el acceso.

## Enviar contactos a Outlook

Tras **Conectar el envío de contactos** aparece el botón **A Outlook** en la
página de detalle de un cliente. Transfiere el cliente como contacto a Outlook
de la cuenta conectada: nombre, persona de contacto, empresa, correo,
teléfono, número de móvil, sitio web y dirección. Una nueva transferencia
actualiza el contacto en lugar de duplicarlo; si se eliminó en Outlook,
WorkDiary lo vuelve a crear. La transferencia solo se hace al pulsar el botón y
solo en esta dirección; para ello hace falta el permiso para editar el cliente.

Los contactos de Outlook de esta cuenta sirven además como directorio al
comparar números de teléfono desconocidos, por ejemplo en la importación de
FRITZ!Box.

## Sincronizar Microsoft To Do

1. Haga clic en **Conectar sincronización de To Do**.
2. Cree una vinculación: elija la **Lista de To Do**, como **Destino** un
   **Proyecto** o el **Kanban global**, seleccione el **Proyecto** si el destino
   es un proyecto, fije la **Dirección** (**Ambas direcciones**, **Solo To Do →
   WorkDiary** o **Solo WorkDiary → To Do**) y haga clic en **Vincular**.
3. La tabla muestra todas las vinculaciones. **Quitar** elimina una; las tareas
   ya sincronizadas se conservan.

Cada lista de To Do puede vincularse una sola vez; una nueva vinculación de la
misma lista sustituye a la anterior. Se sincronizan título, descripción, estado
(abierta, en curso, terminada), prioridad y fecha de vencimiento. La
sincronización se ejecuta cada hora; los cambios en WorkDiary salen además al
momento, y Microsoft avisa al instante de los cambios en las listas que
importan. Si ambas partes han cambiado la misma tarea, se crea un conflicto en
la **Bandeja de conciliación**: el último cambio no gana sin más. WorkDiary
nunca transfiere eliminaciones; las tareas eliminadas en To Do solo se marcan.
Esta sincronización no conoce subtareas, responsables ni secciones.

## Importar de OneNote

1. En **Plugins**, active la opción **Permitir importación de OneNote** en el
   diálogo **Configurar** de **Microsoft 365**. Hasta entonces la tarjeta
   muestra **Desactivado** y la conexión no solicita acceso a los blocs de
   notas.
2. Haga clic en **Conectar OneNote**. El acceso es de solo lectura.
3. **Ir a «Conocimiento»** lleva a la importación: allí **Importar OneNote**
   incorpora un bloc de notas una vez o bajo demanda como notas o artículos de
   conocimiento. El bloc se convierte en una colección y sus secciones en
   subcolecciones. No hay escritura de vuelta ni sincronización continua.

## Aplicación Entra y concesión a nivel de tenant

Si una directiva de su tenant de Microsoft impide que los usuarios den su
consentimiento por sí mismos, un administrador de Entra concede los permisos
una vez para toda la organización: **Conceder para la organización (admin
consent)**. El inicio de sesión exige un rol de administrador de Entra en el
tenant de destino. La concesión abarca calendario, envío de correo, contactos,
tareas y entrada de documentos y, con la importación de OneNote activada,
también la lectura de los blocs de notas. Después, los usuarios se conectan
sin solicitud de consentimiento propia.

**URI de redirección para un registro de aplicación propio** enumera las
direcciones que una aplicación propia debe registrar como URI de redirección de
tipo «Web»: para calendario, envío de correo, contactos, tareas, OneNote,
entrada de documentos, admin consent, solo en la aplicación de la instalación
el destino de copia de seguridad, y el **Almacenamiento SharePoint**, que usa
la misma aplicación mientras el operador no le asigne una propia.

## Otras funciones del plugin

- **Configurar la respuesta automática de Outlook para vacaciones aprobadas**
  (en los ajustes del plugin, desactivada por defecto): en cuanto unas
  vacaciones quedan aprobadas definitivamente, WorkDiary configura la
  respuesta automática en el buzón de la persona. Para ello la aplicación
  necesita el permiso de aplicación MailboxSettings.ReadWrite con admin
  consent. Los errores no frenan la aprobación.
- La **Entrada de documentos en la nube** y los **Destinos de copia de
  seguridad en la nube** usan conexiones propias de Microsoft, que se
  configuran en esas páginas.

## Desconectar y volver a conectar

Cada tarjeta tiene su propia desconexión. Elimina las claves de acceso de esa
conexión; las citas transferidas y los contactos de Outlook se quedan en
Microsoft. Puede volver a conectar en cualquier momento; WorkDiary pone
entonces también a cero el contador de errores. Si una conexión se suspendió
tras errores repetidos consecutivos, el botón de conexión vuelve a aparecer.
Mientras una conexión de calendario falle, una tarea operativa lo indica.

## Problemas habituales

- **Sin botones de conexión:** falta el registro de aplicación (véanse los
  requisitos previos).
- **«El flujo OAuth ha caducado o no es válido. Inténtelo de nuevo.»** El
  inicio de sesión tardó demasiado o se completó en otra sesión. La persona que
  conecta debe terminar el proceso personalmente.
- **«La conexión fue rechazada o cancelada.»** Se denegó el consentimiento. Si
  la cuenta no puede darlo por sí misma, use el admin consent.
- **Insignia Inaccesible:** Microsoft Graph no es accesible o deniega el
  acceso. Compruebe la cuenta y vuelva a conectar.
- **«Error al enviar la prueba: …»** Con una dirección de remitente distinta
  suele faltar el permiso «Enviar como».
- **«La lista de To Do seleccionada ya no está disponible.»** La lista se
  eliminó en To Do o no pertenece a la cuenta conectada.
- **Aviso sobre conexiones secundarias:** si la comprobación de estado en
  **Plugins** indica que las conexiones secundarias de Microsoft 365 requieren
  atención, hay una conexión de entrada de documentos, copia de seguridad o
  envío de correo con problemas. Vuelva a iniciar sesión allí.
