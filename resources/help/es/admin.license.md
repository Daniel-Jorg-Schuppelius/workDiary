---
title: "Gestión de licencias"
topic: admin.license
version: 1
audience:
    - admin
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
Además puedes sobrescribir **feature flags**, emitir nuevas licencias
(si tu instalación está autorizada) y gestionar el **estado del
inquilino** (prueba/activo/bloqueado): en estado bloqueado se
desactivan las acciones de escritura y el límite de usuarios se aplica
al crear miembros. Las licencias se introducen como claves firmadas,
sin subir archivos.
