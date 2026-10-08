---
title: "Gestión de licencias"
topic: admin.license
version: 3
keywords:
    - clave de licencia
    - plan
    - suscripción
    - cambiar de plan
    - actualizar plan
    - módulos adicionales
    - límite de usuarios
    - periodo de prueba
    - licencia caducada
    - cuenta bloqueada
    - feature flags
    - datos de facturación
audience:
    - admin
    - geschaeftsfuehrung
related:
    - admin.handbook
    - admin.tenants
---

La página de licencias muestra lo que su instalación tiene permitido:
**Plan** (free/pro/enterprise), **límites de usuarios y de
organizaciones**, **Módulos** habilitados y **fecha de expiración**.

Así se relaciona todo:

- La **licencia es la fuente** del plan y de los módulos adicionales
  (add-ons); la asignación plan → módulos se encuentra en la
  configuración. De este modo, los nuevos módulos de un plan están
  disponibles sin necesidad de volver a emitir la licencia.
- Las **licencias vinculadas a la organización** pueden instalarse y
  eliminarse por organización; si falta una licencia de organización,
  se aplica la licencia global como respaldo.
- **Sin una licencia válida**, la instalación funciona estrictamente en
  el plan Free.

Acciones típicas:

1. Comprobar el estado de la licencia y los módulos.
2. Sobrescribir de forma selectiva los **Indicadores de funciones**
   (interruptores de anulación).
3. **Instalar/eliminar** la licencia de la organización o – si su
   instalación está autorizada para ello – **emitir** nuevas licencias
   (licenciatario, correo electrónico, plan, add-ons, expiración,
   límites, organización, dominio).

Estado del inquilino (SaaS):

- El **Estado del inquilino** indica si la organización está en
  **Periodo de prueba**, **Activo** o **Suspendido**. Si no se ha fijado
  ningún estado, este se deriva del periodo de prueba y de la expiración
  de la licencia (válida / en periodo de gracia / caducada).
- Un administrador de la plataforma puede fijar manualmente el estado en
  **Activo**, **Periodo de prueba** o **Suspendido**, o volver a
  liberarlo mediante *Automático (derivar)*.
- Con el estado **Suspendido** (o con una licencia definitivamente
  caducada) las **acciones de escritura están desactivadas**; la lectura
  sigue siendo posible. Las páginas de licencia y de cierre de sesión
  siguen accesibles para que pueda levantarse el bloqueo.
- El **límite de usuarios** de la licencia se aplica al crear nuevos
  miembros: si se ha alcanzado el límite, la creación se bloquea con un
  aviso.

Conviene saber:

- Las reducciones de plan bloquean módulos mediante el control de
  acceso por plan (plan gating); los contenidos de los módulos sujetos a
  obligación de conservación se mantienen.
- No se sube ningún archivo – las licencias se introducen como claves
  firmadas.

## Datos de facturación y cambio de plan

En «Datos de facturación» gestiona el destinatario de la factura, el
correo electrónico, la dirección, el NIF-IVA y la referencia de pedido para
la facturación por parte del operador. Allí también solicita otro plan o
módulos adicionales; por organización existe una solicitud abierta, que
puede retirar. El operador la resuelve emitiendo una nueva licencia.
