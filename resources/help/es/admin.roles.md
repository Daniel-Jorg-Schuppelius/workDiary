---
title: "Roles y permisos"
topic: admin.roles
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.security
    - roles.admin
---

La gestión de permisos se divide en **Permisos** (catálogo de solo
lectura con el esquema `recurso.acción`), **Roles** (paquetes de
permisos ajustables por organización), **Grupos** (agrupación solo
visual) y **Miembros** (asignación de roles). El flujo típico: crear o
copiar un rol, recortar los permisos, asignarlo a miembros y probar
con una cuenta de prueba. Aplica el principio de privilegios mínimos y
nunca otorgues el rol de administrador global (sin organización) a
través de permisos delegables: actúa en toda la plataforma y es un
riesgo de escalada. En módulos sensibles no existe bypass de admin:
esos permisos deben concederse expresamente, también a los admins.
