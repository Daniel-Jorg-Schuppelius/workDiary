---
title: "Gestión de licencias"
topic: admin.license
version: 2
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

La página de licencias muestra el **plan** (free/pro/enterprise), los
**límites de usuarios/organizaciones**, los **módulos** habilitados y
la **fecha de expiración**. La licencia es la fuente del plan y de los
módulos adicionales; las licencias por organización pueden instalarse
y eliminarse, y sin licencia de organización se aplica la global como
respaldo. Sin licencia válida, la instalación funciona en el plan Free.
Además puede sobrescribir **feature flags**, emitir nuevas licencias
(si su instalación está autorizada) y gestionar el **estado del
inquilino** (prueba/activo/bloqueado): en estado bloqueado se
desactivan las acciones de escritura y el límite de usuarios se aplica
al crear miembros. Las licencias se introducen como claves firmadas,
sin subir archivos.

## Datos de facturación y cambio de plan

En «Datos de facturación» gestiona el destinatario de la factura, el
correo, la dirección, el NIF-IVA y la referencia de pedido para la
facturación por parte del operador. Allí también solicita otro plan o
módulos adicionales; cada organización tiene una solicitud abierta, que
puede retirar. El operador la resuelve emitiendo una nueva licencia.
