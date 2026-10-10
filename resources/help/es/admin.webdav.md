---
title: "Almacenamiento WebDAV"
topic: admin.webdav
version: 3
keywords:
    - WebDAV
    - Nextcloud
    - ownCloud
    - copiar documentos
    - almacenamiento de archivos
    - archivar facturas
    - archivar actas
    - contraseña de aplicación
    - reglas de carpetas
    - conflicto de copia
    - direcciones privadas
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - documents.manage
    - invoices.manage
    - protocols.sign
    - backup-targets.overview
---

La página **WebDAV** (título de página **Almacenamiento WebDAV**) copia como
archivos los documentos aprobados y, si lo desea, las facturas emitidas y las
actas firmadas en un almacenamiento WebDAV externo, por ejemplo Nextcloud u
ownCloud. Para cada archivo, WorkDiary guarda un comprobante de transferencia
(suma de verificación, hora, destino). WorkDiary sigue siendo el sistema de
referencia: no hay canal de retorno, y los cambios en los archivos copiados
dentro del almacenamiento aparecen como conflicto en lugar de adoptarse sin
aviso. Encontrará la página en el menú del sistema (el engranaje **Sistema**
en la cabecera) en **Plugins** → **WebDAV**, en cuanto el plugin esté activo.

## Requisitos

- El plugin está activado para su organización: **Sistema** → **Plugins** →
  **Plugins** y luego **Activar** en la entrada WebDAV. El almacenamiento en
  sí se configura en la página **WebDAV**, no en el diálogo del plugin.
- La página está reservada a los administradores.
- Necesita una cuenta en el almacenamiento con permiso de escritura en la
  carpeta de destino y una contraseña de aplicación (Nextcloud: Ajustes →
  Seguridad → Contraseña de aplicación).
- El almacenamiento debe ser accesible públicamente. Si el servidor está en
  su propia red, active **Permitir direcciones privadas/internas** (véase más
  abajo).
- Por organización existe exactamente un almacenamiento WebDAV.

Esta página no sirve como destino de copias de seguridad. Un destino de copia
de seguridad WebDAV se configura en **Destinos de copia de seguridad en la
nube**.

## Configurar el almacenamiento

En la sección **Almacenamiento** rellena:

- **Etiqueta**: un nombre a su elección.
- **URL de la colección**: la carpeta WebDAV completa en la que escribe
  WorkDiary, en Nextcloud por ejemplo …/remote.php/dav/files/USUARIO/WorkDiary.
  La dirección debe empezar por http:// o https://; cree la carpeta antes en
  el almacenamiento.
- **Nombre de usuario** y **Contraseña de aplicación**: la contraseña es
  obligatoria al guardar por primera vez y se guarda cifrada; más adelante,
  un campo vacío conserva la contraseña guardada.
- **Carpeta predeterminada**: subcarpeta para los documentos sin regla de
  carpeta propia (prerrellenada con Dokumente).
- **Permitir direcciones privadas/internas**: actívelo solo si el servidor
  WebDAV está en su propia red (por ejemplo 192.168.x.x). Sin este
  interruptor, WorkDiary rechaza las direcciones internas ya al guardar. La
  activación queda registrada. Si el operador de su instalación ha bloqueado
  esta autorización, el interruptor no tiene efecto.
- **Activo**: activa o desactiva el almacenamiento.
- **Contenido reflejado**: **Documentos (DMS)**, **Facturas (PDF)**, **Actas
  (PDF)**.
- **Tipo de documento → carpeta**: una subcarpeta propia por tipo de
  documento, véase más abajo.

**Guardar** aplica los datos. Cuando el almacenamiento está activo, la página
muestra su estado (por ejemplo **Estado correcto**) y **Probar conexión**.

## Qué se copia y cuándo

- **Documentos:** un documento se copia en cuanto tiene el estado **Activo**
  con un archivo, y de nuevo con cada versión nueva. Los cambios en los datos
  sin versión nueva no provocan una subida. Esto solo rige con **Documentos
  (DMS)** marcado; si no hay ninguna fuente marcada, los documentos cuentan
  como elegidos.
- **Facturas (PDF):** con esta casilla marcada, cada factura se deposita una
  vez como PDF al pasar a **Emitida**.
- **Actas (PDF):** con esta casilla marcada, cada acta se deposita como PDF al
  firmarse (estado **Firmado**).
- La transferencia se ejecuta en segundo plano mediante una cola y se repite
  si hay errores de conexión. WorkDiary no vuelve a subir contenidos sin
  cambios.
- **Copiar ahora** vuelve a poner en cola todo lo de las fuentes marcadas:
  documentos aprobados, facturas emitidas y actas firmadas, algo útil tras
  la configuración, también para documentos anteriores. WorkDiary no vuelve
  a subir contenidos ya copiados.
- No existe una ejecución programada; la copia sigue a los cambios en
  WorkDiary.

## Carpetas y nombres de archivo

- Los documentos se guardan en la carpeta de su tipo de documento definida en
  **Tipo de documento → carpeta** o, si no, en la **Carpeta predeterminada**,
  ambas relativas a la URL de la colección. El archivo se llama document-
  seguido del número del documento y la extensión original, por ejemplo
  document-42.pdf.
- La selección nombra los tipos de documento por su denominación, por
  ejemplo Contrato o Factura. Siempre hay tres filas libres; WorkDiary
  descarta las filas sin tipo o sin subcarpeta.
- Las facturas se guardan en invoices/año/número-de-factura.pdf y las actas en
  protocols/año/protocol-número.pdf, directamente bajo la URL de la colección,
  no en la carpeta predeterminada.
- WorkDiary crea por sí mismo las subcarpetas que falten.

## Conflictos

Antes de subir una versión nueva, WorkDiary comprueba si el archivo del
almacenamiento cambió desde la última copia. Si es así, no sobrescribe nada y
crea un conflicto en la Bandeja de conciliación: «Cambio externo detectado —
copia en pausa». Allí elige:

- **Sobrescribir remoto**: el archivo del almacenamiento recibe el estado de
  WorkDiary; el cambio externo se pierde.
- **Importar como nueva versión**: el estado del almacenamiento se convierte en
  la nueva versión del documento en WorkDiary.
- **Desvincular copia**: este documento deja de copiarse de forma
  permanente; el almacenamiento sigue activo para todos los demás.

Para los PDF de facturas y actas solo existe **Sobrescribir remoto**: las
facturas emitidas y las actas firmadas no se pueden modificar, WorkDiary
vuelve a depositar su PDF. Si quiere conservar el archivo modificado, elija
**Descartar**.

La Bandeja de conciliación está abierta a los administradores y a
contabilidad.

## Desconectar

**Desconectar** desactiva el almacenamiento. Los archivos ya copiados se
quedan en el almacenamiento. Para volver a activarlo, marque **Activo** y
guarde.

## Errores frecuentes

- «La URL de la colección debe empezar por http:// o https://.»: introduzca
  la dirección completa.
- «Un almacenamiento nuevo requiere una contraseña de aplicación.»: falta la
  contraseña al guardar por primera vez.
- **Estado defectuoso** con «Almacenamiento WebDAV no accesible o credenciales
  no válidas.»: compruebe la URL de la colección, el nombre de usuario, la
  contraseña de aplicación y que la carpeta exista. Un error de WebDAV con
  RuntimeException suele indicar una dirección de una red interna sin
  autorización.
- «La URL de la colección apunta a una dirección privada/interna.»: si el
  servidor está en su propia red, active **Permitir direcciones
  privadas/internas**. Si el operador ha bloqueado esta autorización, el
  almacenamiento necesita una dirección accesible públicamente.
- «No hay ningún almacenamiento WebDAV activo.» con **Copiar ahora**: el
  almacenamiento está desactivado o incompleto.
- Faltan facturas o actas en el almacenamiento: la casilla correspondiente en
  **Contenido reflejado** no estaba marcada al emitirlas o firmarlas.
- Un documento ya no se actualiza: hay un conflicto abierto en la bandeja, o
  se desvinculó su copia.
