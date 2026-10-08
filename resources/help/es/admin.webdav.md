---
title: "Almacenamiento WebDAV"
topic: admin.webdav
version: 1
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
- El almacenamiento debe ser accesible públicamente. WorkDiary rechaza las
  direcciones de una red interna.
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
  sin versión nueva no provocan una subida. El almacenamiento copia siempre
  los documentos aprobados mientras esté activo, aunque **Documentos (DMS)**
  no esté marcado.
- **Facturas (PDF):** con esta casilla marcada, cada factura se deposita una
  vez como PDF al pasar a **Emitida**.
- **Actas (PDF):** con esta casilla marcada, cada acta se deposita como PDF al
  firmarse (estado **Firmado**).
- La transferencia se ejecuta en segundo plano mediante una cola y se repite
  si hay errores de conexión. WorkDiary no vuelve a subir contenidos sin
  cambios.
- **Copiar ahora** vuelve a poner en cola todos los documentos aprobados en
  ese momento, algo útil tras la configuración. Este botón no incluye
  facturas ni actas; estas solo se copian a partir de la configuración, al
  emitirse o firmarse.
- No existe una ejecución programada; la copia sigue a los cambios en
  WorkDiary.

## Carpetas y nombres de archivo

- Los documentos se guardan en la carpeta de su tipo de documento definida en
  **Tipo de documento → carpeta** o, si no, en la **Carpeta predeterminada**,
  ambas relativas a la URL de la colección. El archivo se llama document-
  seguido del número del documento y la extensión original, por ejemplo
  document-42.pdf.
- La selección de tipos de documento muestra por ahora el código corto en
  inglés, por ejemplo contract para contratos o invoice para facturas. Siempre
  hay tres filas libres; WorkDiary descarta las filas sin tipo o sin
  subcarpeta.
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
  RuntimeException suele indicar una dirección de una red interna.
- «No hay ningún almacenamiento WebDAV activo.» con **Copiar ahora**: el
  almacenamiento está desactivado o incompleto.
- Faltan facturas o actas en el almacenamiento: la casilla correspondiente en
  **Contenido reflejado** no estaba marcada al emitirlas o firmarlas.
- Un documento ya no se actualiza: hay un conflicto abierto en la bandeja, o
  se desvinculó su copia.
