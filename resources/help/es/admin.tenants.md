---
title: "Organizaciones e inquilinos"
topic: admin.tenants
version: 3
keywords:
    - gestión de inquilinos
    - multi-tenant
    - multiempresa
    - mandante
    - crear organización
    - añadir empresa
    - eliminar organización
    - desactivar organización
    - cambiar de organización
    - exportación de datos
    - purga
    - cambiar plan
    - lista de organizaciones
audience:
    - admin
related:
    - admin.handbook
    - admin.license
    - admin.roles
    - admin.organization-settings
---

Aquí administra las organizaciones (inquilinos); cada una es una
unidad aislada y todos los datos pertenecen exactamente a un
inquilino. Las acciones típicas son crear/editar, desactivar y
reactivar (reversible), exportar datos (art. 20 RGPD), eliminar
definitivamente (purge, art. 17 RGPD) y cambiar de contexto de
organización como admin global. El plan o la licencia de la
organización determina los módulos habilitados (véase el capítulo
**Licencia**). El **purge es irreversible**: ofrezca antes un exporte y
compruebe las obligaciones de conservación; desactivar es la
alternativa segura si solo debe cerrarse el acceso.

Aprobaciones: en la sección del mismo nombre define qué rol ve las etapas
de aprobación de una negociación contractual en «Aprobaciones», por tipo
de etapa (comercial, técnica, RR. HH.). Si se deja vacío, se aplica el
valor predeterminado: Contabilidad, Jefe de equipo, Administración de
personal. La aprobación desde el expediente no se ve afectada.

## Lista de organizaciones y su propia organización

La lista **Organizaciones** con todos los inquilinos está reservada a la
operación de la plataforma: en el menú del sistema (el icono de engranaje
**Sistema** de la cabecera) en **Organización** → **Organizaciones**. Los
operadores de la plataforma sin organización propia encuentran además en el
menú de administración (icono **Administración** de la cabecera), en
**Personal**, la entrada **Empleados**, que también lleva a la lista de
organizaciones. Cuando un administrador así abre la lista, WorkDiary asigna su
cuenta a la primera organización creada; a partir de entonces, **Empleados**
lleva a la gestión de miembros de esa organización.

Los administradores de una organización editan su propia organización en el
menú del sistema en **Organización** → **Organización** (diálogo **Editar
organización**; detalles en el tema «Organización y configuración»). El
**Plan** y el estado activo solo los fija la operación de la plataforma: los
administradores de la organización ven el plan en la sección **Plan y estado**
solo a título informativo («El plan sigue la licencia y lo gestiona el
operador.») y no tienen interruptor para el estado activo.
