---
title: "Clientes & proveedores"
topic: contacts.manage
version: 10
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
