---
title: "Conexiones de envío DHL, UPS y FedEx"
topic: admin.shipping-carriers
version: 1
keywords:
    - envío
    - DHL
    - UPS
    - FedEx
    - etiqueta de envío
    - imprimir etiqueta de paquete
    - etiqueta de devolución
    - número de seguimiento
    - empresa de paquetería
    - portal de clientes empresa
    - sandbox
    - transportista
audience:
    - admin
modules:
    - module.versand
related:
    - admin.integrations
    - admin.plugins
    - manufacturing.orders
    - claims.overview
    - admin.organization-settings
    - admin.operations
---

La página **Envío y logística**, que en el menú figura como **Envío**, guarda
las credenciales de las empresas de paquetería DHL Paket, UPS y FedEx. Con una
conexión activa crea directamente en WorkDiary etiquetas de envío para las
entregas y etiquetas de devolución para las devoluciones. Hay una conexión por
empresa de paquetería y organización; las contraseñas y claves se guardan
cifradas.

## Requisitos previos

- Su licencia incluye el módulo Envío y logística.
- El plugin de la empresa de paquetería está activado en **Plugins**: **DHL
  Paket**, **UPS** o **FedEx**. A continuación aparece la entrada **Envío** en
  el menú del sistema (icono de engranaje **Sistema**), dentro del grupo
  **Plugins**.
- Dispone de un acceso de cliente empresa con la empresa de paquetería:
  - **DHL:** usuario y contraseña del portal de clientes empresa de DHL, una
    clave API habilitada por DHL (dhl-api-key) y el número de facturación.
    Para las etiquetas de devolución, además el ID del destinatario de
    devoluciones, que crea en el portal de clientes empresa.
  - **UPS:** ID de cliente y secreto de cliente de una aplicación de
    desarrollador de UPS y su número de cuenta de UPS (número de remitente).
  - **FedEx:** ID de cliente y secreto de cliente de una aplicación de
    desarrollador de FedEx y su número de cuenta de FedEx.
- Para UPS y FedEx, WorkDiary toma la dirección del remitente de los ajustes de
  la organización, sección **Factura electrónica (XRechnung)**: **Nombre de la
  empresa** (si está vacío, el nombre de la organización), **Calle y número**,
  **Código postal** y **Ciudad**. Si falta alguno de estos datos, la etiqueta
  falla.
- La página está reservada a los administradores de su organización.

## Crear o cambiar una conexión

En la sección **Añadir / editar conexión**:

1. **Transportista**: DHL, UPS o FEDEX.
2. **Denominación**: un nombre con el que la conexión se ofrecerá después al
   crear etiquetas, por ejemplo «DHL envío almacén».
3. **Usuario / ID de cliente** y **Contraseña / secreto de cliente**.
4. **Clave API (solo DHL: dhl-api-key)**.
5. **ID del destinatario de devoluciones (solo DHL)**: necesario para las
   etiquetas de devolución de DHL.
6. **Número de facturación / de cuenta**: en DHL el número de facturación, en
   UPS el número de remitente, en FedEx el número de cuenta.
7. **Sandbox / entorno de pruebas**: conecta con el entorno de pruebas de la
   empresa de paquetería; allí no se generan envíos reales.
8. **Activo** y **Guardar**.

Para una conexión nueva son obligatorios el usuario/ID de cliente y la
contraseña/secreto de cliente; en DHL, además, la clave API.

Para hacer cambios, guarde de nuevo el formulario con el mismo transportista;
así se actualiza la conexión existente. El formulario empieza siempre vacío:

- Los campos de usuario, contraseña, clave API e ID del destinatario de
  devoluciones que deje vacíos conservan el valor guardado.
- **Denominación** y **Número de facturación / de cuenta** se introducen cada
  vez: un número de facturación vacío se borra.
- **Sandbox / entorno de pruebas** y **Activo** valen tal como estén marcados
  al guardar. Por tanto, una conexión sandbox pasa a producción si no vuelve a
  marcar la casilla.

## Conexiones existentes

La lista **Conexiones existentes** muestra de cada conexión el transportista,
la denominación, el **Modo** (**Sandbox** o **Producción**) y el estado
(**Activo** o **Inactivo**). **Desactivar** apaga una conexión; a partir de ahí
deja de ofrecerse. Para reactivarla, guárdela de nuevo con **Activo**
marcado.

## Crear etiquetas

- **Etiqueta de envío para entregas:** en **Órdenes de fabricación**, la página
  de detalle de una orden contiene la sección **Entregas**. En una entrega con
  cliente, elija la conexión, indique (si no hay bultos registrados) el peso en
  gramos y, opcionalmente, largo, ancho y alto en centímetros, y haga clic en
  **Enviar**. UPS y FedEx solo usan las medidas si se indican las tres. Los
  bultos registrados aportan ellos mismos peso y medidas. El destinatario es el
  cliente de la entrega. Después la entrega muestra el estado **Etiqueta
  creada** con la empresa de paquetería y el número de seguimiento. Hay una
  orden de envío por entrega.
- **Etiqueta de devolución:** en los **Expedientes de reclamación**, elija para
  una devolución en estado **Anunciado** la conexión, indique el peso y haga
  clic en **Crear etiqueta de devolución**. El remitente es el cliente; su
  dirección debe incluir calle, código postal y ciudad. Con **Descargar
  etiqueta** obtiene el archivo; si las devoluciones están habilitadas para el
  cliente en el portal de clientes, también él puede descargar allí la
  etiqueta.
- **Permisos:** las etiquetas de envío las crea quien puede editar la orden de
  fabricación; las de devolución, quien tiene el permiso **Inspeccionar y
  almacenar devoluciones**.

UPS entrega la etiqueta como imagen (GIF) y FedEx como PDF. Si la empresa de
paquetería rechaza el pedido, WorkDiary descarta el borrador y usted puede
volver a intentarlo tras la corrección.

## Límites

- Una conexión por empresa de paquetería y organización.
- Por defecto, los envíos de DHL salen como DHL Paket nacional; solo el
  operador de la instalación puede configurar otro producto.
- Los documentos aduaneros para envíos fuera de la UE se crean por separado en
  la entrega (véase la ayuda sobre las órdenes de fabricación).

## Problemas habituales

- **«Una nueva conexión requiere usuario/ID de cliente y contraseña/secreto de
  cliente (DHL además: clave API).»** Complete las credenciales que faltan.
- **No hay conexión para elegir:** no existe ninguna conexión activa, o la
  entrega no tiene cliente o ya tiene una orden de envío.
- **«No hay una conexión activa configurada para el transportista
  seleccionado.»** La conexión se ha desactivado entretanto.
- **«No se pudo crear la etiqueta de envío: …»** Compruebe las credenciales, el
  número de facturación o de cuenta, la opción **Sandbox / entorno de pruebas**
  y la dirección del destinatario. En UPS y FedEx suele faltar la dirección del
  remitente en los ajustes de la organización; en las etiquetas de devolución
  de DHL, el ID del destinatario de devoluciones.
- **«La etiqueta de devolución necesita la dirección del cliente (calle, código
  postal, ciudad).»** Complete la dirección en la ficha del cliente.
- **Comprobar credenciales:** la comprobación de estado de la empresa de
  paquetería se ejecuta en **Plugins**. Si una conexión falla, WorkDiary
  notifica la conexión con problemas en **Tareas operativas**.
