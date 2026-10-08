---
title: "Almacenamiento SharePoint"
topic: admin.sharepoint
version: 1
keywords:
    - SharePoint
    - SharePoint Online
    - biblioteca de documentos
    - replicar documentos
    - archivar en SharePoint
    - Microsoft 365
    - elegir sitio
    - replicación
    - justificante de entrega
    - archivar facturas
    - conflicto de replicación
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.msgraph
    - documents.manage
    - admin.integration-inbox
    - cloud-intake.overview
---

La página **Almacenamiento SharePoint** replica los documentos aprobados de
WorkDiary en una biblioteca de documentos de SharePoint Online a través de
Microsoft Graph y, si lo desea, también los PDF de las facturas emitidas y de
los protocolos firmados. WorkDiary sigue siendo el sistema de referencia: desde
SharePoint no vuelve nada, y los cambios en los archivos replicados dentro de
SharePoint se muestran como conflicto, nunca se adoptan en silencio. En cada
transferencia WorkDiary registra un justificante de entrega (suma de
comprobación, momento, destino).

Traer archivos de SharePoint a WorkDiary en modo de solo lectura es otra
función: la **Entrada de documentos en la nube**.

## Requisitos previos

- El plugin **SharePoint** está activado en **Plugins**. A continuación aparece
  la entrada **Almacenamiento SharePoint** en el menú del sistema (icono de
  engranaje **Sistema**), dentro del grupo **Plugins**.
- Existe un registro de aplicación en Microsoft Entra ID con ID de cliente y
  secreto de cliente. O bien el operador ha guardado una aplicación para toda
  la instalación, o bien su organización usa su propia aplicación desde los
  ajustes del plugin **Microsoft 365** (**ID de cliente (registro de
  aplicación propio)**, **Secreto de cliente**, **Tenant (ID de
  directorio)**). Una aplicación propia debe conocer su dirección de WorkDiary
  con la ruta /admin/sharepoint/oauth/callback como URI de redirección de tipo
  «Web». Si falta la aplicación, la página muestra un aviso en lugar del botón
  de conexión.
- Necesita una cuenta de Microsoft 365 con permiso de escritura en la
  biblioteca de destino. La conexión trabaja con los permisos de esa cuenta.
  Si el operador ha limitado el acceso a sitios autorizados uno a uno
  (Sites.Selected), un administrador del tenant debe autorizar además el sitio
  deseado.
- La página está reservada a los administradores de su organización. Cada
  organización tiene una conexión de SharePoint.

## Conectar

1. Haga clic en **Conectar con Microsoft 365**. Se abre el inicio de sesión de
   Microsoft; inicie sesión y acepte los permisos.
2. Microsoft le devuelve a la página. El mensaje «Conectado con Microsoft 365.
   Ahora elija sitio + biblioteca.» confirma la conexión.

El proceso debe terminarlo la misma persona que lo inició, en la misma sesión.
De lo contrario aparece «El flujo OAuth expiró o no es válido»; en ese caso
inicie de nuevo la conexión.

## Elegir el destino: sitio y biblioteca de documentos

1. En la sección **Destino: sitio + biblioteca de documentos**, escriba en el
   campo **Buscar sitio** el nombre o una palabra clave del sitio y haga clic
   en **Buscar**.
2. Haga clic en el sitio dentro de la lista de resultados. Queda marcado como
   **Seleccionado** y WorkDiary carga sus bibliotecas de documentos.
3. En **Biblioteca de documentos**, elija la biblioteca y haga clic en
   **Guardar**. **Destino actual** muestra después el sitio y la biblioteca.

Al guardar, WorkDiary comprueba el sitio y la biblioteca con Microsoft; una
biblioteca que no pertenece al sitio elegido se rechaza.

## Reglas de carpetas y contenidos replicados

En la sección **Reglas de carpetas + orígenes** define qué se replica y dónde:

- **Carpeta predeterminada** (rellenada con «Dokumente»): subcarpeta de la
  biblioteca para todos los documentos sin regla propia.
- **Activo**: activa o desactiva la replicación.
- **Contenidos replicados**: **Documentos (DMS)**, **Facturas (PDF)** y
  **Protocolos (PDF)**. Sin selección solo se replican los documentos.
- **Tipo de documento → carpeta**: en cada fila elija un tipo de documento e
  indique una subcarpeta relativa a la biblioteca. Las filas vacías se
  ignoran; tras cada guardado hay tres filas vacías más. Los tipos aparecen en
  la lista con su nombre corto en inglés, por ejemplo contract para los
  contratos o invoice para las facturas.

Después haga clic en **Guardar**.

Así guarda WorkDiary los archivos:

- **Documentos** en la carpeta de su tipo o en la carpeta predeterminada. El
  nombre del archivo se compone de «document-», un número interno y la
  extensión; así una versión nueva sustituye el mismo archivo.
- **Facturas** en la carpeta invoices, con una subcarpeta por año; el nombre del
  archivo es el número de factura.
- **Protocolos** en la carpeta protocols, con una subcarpeta por año.

Las facturas y los protocolos no siguen las reglas de carpetas.

## Cuándo se replica

- **Automáticamente ante eventos:** cuando un documento recibe el estado
  **Activo** (aprobado) o una versión nueva, WorkDiary transfiere esa versión.
  Los simples cambios de metadatos no provocan una nueva transferencia. Cuando
  se emite una factura o se firma un protocolo, se envía su PDF, siempre que el
  contenido correspondiente esté seleccionado.
- **En segundo plano con reintentos:** la transferencia pasa por una cola. Si
  falla, se repite automáticamente; ningún archivo se escribe dos veces.
- **Replicar ahora:** pone en cola todos los documentos activos de la
  organización, por ejemplo tras la configuración inicial. WorkDiary omite los
  archivos sin cambios. Este botón no incluye facturas ni protocolos; estos se
  transfieren al emitirse o firmarse.

No hay una planificación fija. WorkDiary solo lee de SharePoint para comprobar
si un archivo replicado se ha modificado allí.

## Resolver conflictos

Si un archivo replicado se modificó en SharePoint, WorkDiary no lo
sobrescribe. En su lugar aparece una entrada en la **Bandeja de conciliación**
con el aviso «Cambio externo detectado — replicación pausada (sin
sobrescritura).» Para los documentos de la gestión documental hay tres
acciones:

- **Sobrescribir remoto**: la versión de WorkDiary sustituye el archivo en
  SharePoint; el cambio hecho allí se pierde.
- **Importar como nueva versión**: la versión de SharePoint se adopta como
  versión nueva del documento.
- **Desvincular la replicación**: este documento concreto deja de replicarse;
  la conexión sigue activa.

La **Bandeja de conciliación** está abierta a las personas autorizadas a
gestionar la facturación.

## Desconectar y volver a conectar

**Desconectar** elimina las claves de acceso de la conexión. Los archivos ya
replicados permanecen en SharePoint. El destino y las reglas de carpetas
quedan guardados; tras un nuevo **Conectar con Microsoft 365** todo continúa
con los mismos ajustes.

## Problemas habituales

- **Sin botón de conexión:** la página indica que falta el registro de
  aplicación. Guarde el ID de cliente y el secreto de cliente (véanse los
  requisitos) o diríjase al operador.
- **Inicio de sesión cancelado:** «Microsoft no devolvió un código de
  autorización»: se canceló el inicio de sesión o se denegó el consentimiento.
  Si su tenant exige el consentimiento de un administrador, un administrador de
  Entra debe conceder el permiso a la aplicación.
- **No se encuentran sitios:** revise el término de búsqueda. Con acceso
  limitado, el administrador del tenant debe autorizar el sitio.
- **Sitio o biblioteca rechazados:** «El sitio elegido no es accesible o no
  está autorizado.» o «No se encontraron bibliotecas de documentos en este
  sitio.»: la cuenta conectada no tiene acceso, o el sitio no tiene biblioteca.
- **Estado Inactivo, falta Replicar ahora:** la conexión está desconectada,
  **Activo** está desactivado, no hay biblioteca elegida, o la conexión se
  suspendió tras errores repetidos consecutivos. Una vez corregida la causa,
  **Desconectar** y volver a conectar ponen a cero el contador de errores.
- **Comprobar el estado:** junto al título de la página figura el último estado
  comprobado; **Probar conexión** lo comprueba al momento.
