---
title: "Entrada de documentos en la nube"
topic: cloud-intake.overview
version: 5
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

**Prueba de regla e inicio:** cada regla necesita una ruta de prueba, por ejemplo una carpeta de cliente real. Al guardar, WorkDiary comprueba si la ruta coincide con el patrón, qué variables se reconocen y qué destino resulta; sin prueba superada no se guarda nada. Una referencia de destino fija asigna todos los archivos de una regla a un cliente, proyecto, pedido, activo o contrato concreto. Las reglas activas de una conexión no pueden solaparse: patrones iguales o una ruta de prueba que coincide con dos reglas bloquean la activación. Una conexión solo inicia su primera importación cuando usted ha revisado la vista previa y confirmado «Iniciar importación»; una nueva carpeta raíz exige una nueva confirmación.

**Soberanía de datos:** Si, según la soberanía de datos, otro sistema lidera los documentos, la conexión no incorpora archivos al DMS; cada archivo se convierte en un caso en la bandeja de integraciones, sin justificante de entrega. **Asumir una vez** vuelve a descargar el archivo y lo crea según las reglas. Las reglas de facturas recibidas, pedidos y paquetes de licitación no se ven afectadas.

## Inicio de sesión caducado, bloqueado o desactivado

Si el proveedor rechaza el inicio de sesión (caducado o revocado), la conexión muestra **Reautenticación necesaria**. Si faltan permisos de lectura al iniciar sesión, muestra **Bloqueada**; tras errores repetidos hasta el umbral de desactivación, **Desactivada**. En los tres casos la importación queda en pausa y la tarjeta ofrece **Volver a iniciar sesión**. Solo un nuevo inicio de sesión vuelve a activar la conexión; cambiar la carpeta o las reglas no lo hace.
