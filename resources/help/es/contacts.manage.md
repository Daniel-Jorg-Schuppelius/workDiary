---
title: "Clientes & proveedores"
topic: contacts.manage
version: 17
keywords:
    - Vista previa como cliente
    - Contraseña inicial
    - Crear acceso
    - ficha de cliente
    - maestro de clientes
    - maestro de proveedores
    - crear cliente
    - crear proveedor
    - deudor
    - acreedor
    - número de deudor
    - fusionar duplicados
    - importar clientes
    - agenda de contactos
    - socio comercial
    - CRM
    - portal de clientes
    - acceso al portal
audience: []
modules:
    - module.vertrieb
schema: process
related:
    - admin.number-formats
    - projects.manage
    - invoices.manage
    - admin.import
    - communication.notes
---

## Objetivo y contexto

Clientes y proveedores son los datos maestros centrales de WorkDiary:
proyectos, órdenes, facturas, comunicación, viajes y análisis cuelgan
de ellos. Unos datos limpios deciden si los procesos posteriores —
del registro de tiempos a la entrega DATEV — funcionan sin retrabajo.

## Requisitos

- El derecho a gestionar clientes o proveedores (normalmente
  administración o ventas).
- Para importar en vez de crear a mano: el asistente de importación
  CSV.
- Identificadores externos (número de deudor, códigos de las
  integraciones de facturación) si se entregan documentos.

## Procedimiento recomendado

1. **Buscar antes de crear:** compruebe si el socio comercial ya
   existe — así no nacen duplicados. Los duplicados existentes se
   pueden fusionar; el historial acompaña.
2. Cree el contacto con nombre, dirección e interlocutores.
   Si, según los **Formatos de numeración**, un programa de contabilidad
   asigna los números de cliente, el campo queda bloqueado: el cliente se
   transfiere al programa al crearlo y recibe su número.
   Registre la persona de contacto con tratamiento, título, nombre, otros
   nombres y apellido; cada fila de la lista de personas de contacto tiene
   los mismos campos. Los programas de contabilidad los reciben por separado.
3. Complete datos de pago y facturación e identificadores externos —
   dirigen la facturación y la entrega contable.
4. Vincule proyectos, ubicaciones y acuerdos según vayan surgiendo.
   Una nueva ubicación toma como propuesta la dirección, el país y las coordenadas del cliente elegido.

![Lista de clientes con números, datos de contacto, tarifas horarias y número de proyectos](media/kunden/kundenliste.png)
*La lista de clientes: datos maestros, tarifa horaria y proyectos vinculados por socio.*

**Organismos públicos y perceptores de tributos:** si registra Hacienda,
las cajas del seguro de enfermedad, la mutua de accidentes de trabajo o el
ayuntamiento como proveedores para que la contabilidad pueda contabilizar
impuestos y cotizaciones, marque allí **Ocultar en las evaluaciones**. Los
comprobantes se conservan; los análisis de proveedores dejan fuera a estas
entidades. En los clientes, la misma casilla actúa sobre los análisis de
clientes y proyectos.

**Comunicación:** registre llamadas, correos y compromisos como nota de
comunicación en el cliente o proveedor. Las notas aparecen en la página de
detalle y en la lista central de notas; una respuesta de acceso de protección
de datos sobre un proveedor las enumera con su número y período.

**Accesos al portal:** en la sección **Accesos al portal** de la ficha del
cliente invita a las personas de contacto al portal de clientes con
**Invitar acceso**; el contacto establece su contraseña mediante el enlace de
la invitación. Mientras la invitación está pendiente o caducada, dispone de
**Reenviar invitación**. En los accesos activos, **Restablecer acceso**
restablece el acceso tras una confirmación: la contraseña anterior deja de ser
válida de inmediato, se cierran todas las sesiones y el contacto recibe una
nueva invitación; los métodos de dos factores configurados se mantienen. Si el
contacto solo ha olvidado su contraseña, no hace falta: la restablece él mismo
en la página de inicio de sesión del portal mediante
**¿Olvidó su contraseña?**. **Desactivar** cierra la sesión del acceso al
instante y bloquea el inicio de sesión, **Reactivar** lo revierte. Las áreas
que ve un acceso las determina la configuración del portal del cliente.

Si un contacto ha perdido todos los métodos de dos factores y los códigos de recuperación, **Restablecer el segundo factor** elimina todos los métodos tras una confirmación de la contraseña y una pregunta de confirmación, y cierra todas las sesiones. El contacto recibe un correo electrónico al respecto y después inicia sesión con su contraseña; si su organización exige la autenticación de dos factores, la configura de nuevo en ese momento. Verifique antes su identidad, por ejemplo devolviéndole la llamada.

**Crear acceso** crea un acceso sin correo de invitación, por ejemplo por teléfono o en una reunión: tras **Nombre** y **Correo electrónico**, la ficha del cliente muestra una sola vez una **contraseña inicial** aleatoria. Entréguela en persona o por teléfono, nunca por correo electrónico; el portal no la envía ni la guarda en texto claro. Hasta el primer inicio de sesión el acceso muestra **Contraseña inicial pendiente**; en el primer inicio de sesión el contacto debe establecer su propia contraseña. Si la ha perdido, **Generar contraseña inicial** crea una nueva (la contraseña anterior deja de ser válida de inmediato y todas las sesiones finalizan). Ambas acciones requieren una confirmación de su contraseña y quedan registradas.

**Ver portal** abre el portal de clientes en una pestaña nueva tal como lo ve este contacto – con sus áreas, documentos y facturas – y ayuda con preguntas como «¿Dónde encuentro …?». La vista previa es solo una vista: las acciones están bloqueadas, las páginas de seguridad y contraseña no son accesibles y no cuenta como inicio de sesión del contacto. Termina con **Finalizar vista previa** en la barra amarilla o en la ficha del cliente, como máximo tras 60 minutos, y se registra con inicio y fin. Requiere el permiso **Ver el portal del cliente como cliente**; sin acceso no hay vista previa: cree primero uno.

**Visibilidad del portal:** junto a las áreas elige aquí el **periodo visible de los tiempos** – **valor predeterminado de la organización** (ajuste **Portal de clientes** en la configuración de la organización, por defecto tres años naturales, incluido el actual), un número fijo de años o **ilimitado**. Es un límite de vista en el portal, no un borrado. La habilitación **Análisis** muestra al cliente indicadores y gráficos – solo de áreas también habilitadas (tiempos en el nivel de detalle configurado, facturas, cuenta de facturación, tickets); como toda nueva habilitación, empieza «desactivada».

**Retirar un acceso:** un acceso que **nunca ha iniciado sesión** (invitación pendiente o caducada, contraseña inicial pendiente) se elimina definitivamente con **Eliminar acceso**: no tiene justificantes asociados. Si el contacto ya ha iniciado sesión, el registro se conserva: desactive el acceso y elija **Retirar acceso**. El nombre, la dirección y las credenciales desaparecen, la dirección de correo queda libre para una nueva invitación, y las confirmaciones de fotos, consultas y solicitudes se conservan como justificante («Antiguo acceso al portal»). Ambas acciones exigen una confirmación de contraseña y se registran en el cliente; los accesos retirados solo aparecen en la línea de resumen bajo la lista. La comprobación de plazos de conservación (área **Accesos al portal**) propone los accesos desactivados para su retirada al cabo de un año.

## Factura electrónica

En el diálogo del cliente, los campos de la factura electrónica están en la
sección **Facturación y presentación**:

- **Formato de e-factura predeterminado**: valor previo para las facturas nuevas
  y la importación de archivos de este cliente, por ejemplo **XRechnung (XML)**
  o **ZUGFeRD (PDF híbrido)**; **Sin estándar (PDF)** mantiene el PDF.
- **Leitweg-ID / referencia del comprador (BT-10)**: dato obligatorio de la
  XRechnung: la Leitweg-ID para las administraciones; en otro caso, una
  referencia indicada por el cliente.
- **Identificador de participante Peppol**: identificador del destinatario para
  el envío por Peppol con la forma esquema:identificador, por ejemplo con el
  NIF-IVA o la Leitweg-ID; vacío significa que no hay envío por Peppol a este
  cliente.
- **Perfil de destinatario de la factura electrónica**: **Administración
  pública** envía solo el XML por correo electrónico, exige la Leitweg-ID,
  incrusta los documentos y adjunta automáticamente el justificante de
  servicios; **Estándar** se aplica al resto de clientes y **Valor de la
  organización** toma el ajuste de la organización.
- **Adjuntar justificante de servicios**: **Según el perfil de destinatario**,
  **Siempre** o **Nunca**; el justificante se genera al emitir la factura a
  partir de los tiempos facturados.
- **Factura electrónica más grande con documentos (MB)**: límite superior para
  la factura electrónica con los documentos incrustados; si está vacío, rige el
  límite del perfil (estándar 15 MB, administración 10 MB).
- **Vía de envío con facturación externa**: si un sistema de facturación lleva
  el número y el contenido de la factura, la envía ese sistema o WorkDiary
  envía la factura con los justificantes; en ese caso desactive el envío
  automático en el sistema de facturación. Solo ve este campo con el permiso
  **Gestionar la configuración financiera**.

Cómo llegan los documentos a la factura electrónica y qué rige para los
clientes de la administración lo describe el tema de ayuda **Facturas &
documentos**.

## Ejemplo práctico

Un proveedor de TI crea «Müller GmbH» con dirección de facturación,
plazo de pago y el número de deudor de la asesoría. Cuando más tarde
se crea el primer lote DATEV, ni un solo documento queda bloqueado
por datos maestros incompletos.

## Errores habituales

- **Crear duplicados** por no buscar antes — análisis e historial se
  fragmentan.
- **Borrar relaciones históricas:** mejor desactivar o archivar los
  contactos en desuso; documentos y tiempos siguen trazables.
- **Cambiar datos de facturación «de paso»:** los cambios valen hacia
  delante; los documentos ya creados conservan a propósito su estado
  documentado.

## Efectos y próximos pasos

Los cambios de datos maestros solo actúan hacia delante — las
entregas cerradas quedan intactas. Después: crear los proyectos del
cliente, revisar los datos de facturación y usar la importación CSV
para volúmenes grandes.

## Proveedor: datos fiscales y autofacturación

En el proveedor se registran el **NIF-IVA** y el **número fiscal**. Si su organización factura ella misma los servicios de un proveedor, indique la fecha del **acuerdo de autofacturación**; solo entonces pueden emitirse autofacturas. La página del proveedor muestra el acuerdo y las últimas autofacturas.

## Facturas y comprobantes en la ficha

Además de las facturas creadas en WorkDiary, la tarjeta **Facturas y
comprobantes** muestra los comprobantes de los programas de contabilidad
conectados para el periodo de la cabecera: Lexoffice, sevDesk, easybill,
orgaMAX, Invoice Ninja, Akaunting, JTL-Wawi e InvoicePlane en clientes y
proveedores. Un programa aparece mientras su importación de
comprobantes esté configurada para su organización; la columna «Fuente» lo
indica. Los comprobantes se asignan durante la importación mediante el vínculo
de contacto, el número de cliente o el nombre del proveedor; si falta el
vínculo, la tarjeta vacía lo indica. Cuando el programa proporciona un PDF
(facturas y abonos de sevDesk, easybill, facturas de orgaMAX), **PDF original**
lo descarga directamente. Los comprobantes de entrada de estos programas también se pueden
asignar como costes de material del cliente y en las compras de suscripciones
y licencias.

## Transferir proveedores a la contabilidad

Si WorkDiary lidera los datos maestros, los proveedores se transfieren al
programa de contabilidad igual que los clientes: Lexoffice, sevDesk, easybill
y orgaMAX. Se transfieren al crearlos, si el programa asigna el número de
proveedor según los **Formatos de numeración**, y tras cambios en un proveedor
ya vinculado. easybill y orgaMAX pueden asignar el número de proveedor; sevDesk
crea un contacto de la categoría «Proveedor» sin número de proveedor propio, de
modo que se mantiene el número de WorkDiary. orgaMAX solo acepta proveedores si
en la administración de orgaMAX está activa la capacidad «Proveedores» y se ha
elegido «WorkDiary lidera»; de lo contrario, orgaMAX lidera los proveedores y
WorkDiary solo los vincula.

Esto no alcanza a los proveedores anteriores a la conexión. **Transferir a
contabilidad** en la ficha del proveedor transfiere uno de ellos al primer
programa activo que acepta proveedores; la operación puede transferirlos todos
de una vez con `php artisan accounting:push-contacts <programa> --suppliers`.
Un proveedor ya vinculado se actualiza, no se crea dos veces; los proveedores
archivados y el proveedor colectivo quedan fuera. Si la contabilidad lidera los
datos maestros, el botón no aparece y el proceso no transfiere nada.

## Datos maestros desde el programa de contabilidad

Si la autoridad sobre los datos maestros está en «Dirige la contabilidad»,
WorkDiary no transfiere contactos. En su lugar, los clientes y proveedores
vienen del programa: Lexoffice y orgaMAX mediante su propia sincronización de
contactos, sevDesk y easybill cada hora según estas reglas:

- Un contacto ya vinculado adopta los datos del programa (nombre, NIF-IVA,
  número fiscal, dirección, correo electrónico y teléfono; en easybill también
  la persona de contacto).
- Una coincidencia segura, por ejemplo por NIF-IVA, se vincula.
- Todo lo dudoso aparece como caso por aclarar en la **Bandeja de
  conciliación**; no se crea nada.

sevDesk indica el rol mediante la categoría del contacto: se importan «Cliente»
y «Proveedor», pero no socios, interesados ni personas de contacto de una
empresa. La dirección es la dirección de sevDesk de la categoría que WorkDiary
usa al transferir; si no, la única dirección. Para correo electrónico,
teléfono, móvil, fax y sitio web vale el marcado como principal; si no, el
único. Si hay varios sin estas marcas, el valor en WorkDiary no cambia, igual
que el país. easybill distingue por número de cliente y de proveedor; un
contacto con ambos números es cliente y proveedor. Los contactos archivados de
easybill quedan fuera.

## Cockpit de cliente y proveedor

La ficha reúne lo que otros módulos saben del contacto: en clientes los
tickets abiertos, los contratos con su próximo plazo, los acuerdos, los plazos
de garantía, los avales, las encuestas con NPS, los dominios y las
suscripciones y licencias; en proveedores los pedidos, los justificantes
obligatorios, los contratos y los plazos. Una sección solo aparece si el
módulo está activado, usted tiene el permiso y existen entradas; las cifras
coinciden con la lista correspondiente. La administración ve los pedidos de
tienda de Etsy y Billbee. Igual que los clientes, los proveedores tienen
campos propios y documentos.

En el proveedor se añaden los **Catálogos** (nombre, número de artículos, última importación; con el derecho de lectura del almacén) y el **Rendimiento**: el scorecard de los últimos doce meses con puntuación global, puntualidad, tasa de reclamaciones, evolución de precios, calidad ISMS, comportamiento ante recursos y plazo de entrega; cada fila abre el scorecard con el mismo periodo. Los contratos de leasing y financiación con el proveedor como arrendador aparecen en la ficha con el derecho a los expedientes de leasing. En el cliente aparecen además las **Citas** reservadas y solicitadas (Calendly, reserva de citas) para quienes pueden abrir la página «Solicitudes de cita».

**Lidera la contabilidad:** si, según la titularidad de datos, la contabilidad gestiona los datos maestros de clientes y proveedores, el formulario muestra un aviso. Todo sigue siendo editable, pero la próxima sincronización sobrescribe los cambios locales; haga los cambios permanentes en el programa contable.
