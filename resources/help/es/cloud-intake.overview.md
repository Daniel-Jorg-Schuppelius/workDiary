---
title: "Entrada de documentos en la nube"
topic: cloud-intake.overview
version: 2
keywords:
    - Paperless-ngx
    - Seafile
    - Dropbox
    - OneDrive
    - SharePoint
    - Google Drive
    - carpeta supervisada
    - importación de documentos
    - carpeta en la nube
    - regla de carpeta
    - importación automática
    - conectar almacenamiento en la nube
audience: []
related:
    - documents.manage
    - admin.integrations
---

WorkDiary LEE documentos de carpetas supervisadas en Dropbox, OneDrive/SharePoint, Google Drive y Nextcloud, así como de Paperless-ngx y Seafile, y los enruta a facturas entrantes o al DMS mediante reglas de carpetas.

**Conexiones:** por proveedor se conecta y confirma una cuenta vía OAuth; después se eligen contenedor (unidad/biblioteca) y carpeta raíz. La importación solo corre con al menos una regla válida.

**Paperless-ngx:** la URL base y el token de API están en la configuración del plugin; «Conectar Paperless-ngx» comprueba la cuenta. Como contenedor elija todos los documentos o una etiqueta, un corresponsal o un tipo de documento. La ruta de un documento es tipo de documento/corresponsal/nombre de archivo («-» si no está asignado); como carpeta raíz basta «/». Se incorpora el archivo original, por lo que se conservan los datos de factura electrónica incrustados; el origen en el registro enlaza con el original en Paperless. Los documentos etiquetados más tarde los detecta la siguiente ejecución al comparar la lista de documentos. El token lee con los permisos de su usuario: necesita acceso de lectura a documentos, etiquetas, interlocutores, tipos de documento y ajustes de interfaz; solo se incorpora lo que ese usuario puede ver.

**Seafile:** la dirección y el token de API están en la configuración del plugin; «Conectar Seafile» comprueba la cuenta. Como contenedor elija una biblioteca y, dentro de ella, la carpeta raíz; las bibliotecas cifradas no se ofrecen. Si nada ha cambiado bajo la carpeta raíz desde la última ejecución, WorkDiary no lee ninguna carpeta. WorkDiary reconoce un archivo por su contenido: renombrarlo o moverlo no crea un justificante nuevo; un archivo con contenido modificado, sí. El origen en el justificante enlaza con el archivo en Seafile. El token de cuenta lo proporciona la API de Seafile: POST a /api2/auth-token/ con nombre de usuario y contraseña; con inicio de sesión en dos pasos, además con la cabecera X-Seafile-OTP y el código actual.

**Reglas:** patrones de ruta con * y ** más variables como {customer_number} asignan archivos a clientes, proyectos, órdenes, activos o contratos existentes — nunca por creación automática. Los casos dudosos van a la bandeja de integración.

**Seguridad:** solo permisos de lectura, tokens cifrados, archivos de origen intactos; los webhooks son solo señales de aviso — la ejecución delta reanudable es la autoritativa.
