---
title: "Roles & permisos"
topic: admin.roles
version: 2
audience:
    - admin
schema: process
related:
    - admin.handbook
    - admin.security
    - org.members
    - roles.admin
---

## Objetivo y contexto

La gestión de permisos decide quién puede ver y hacer qué en
WorkDiary. Se divide en cuatro áreas: **permisos** (catálogo de solo
lectura de derechos granulares con el esquema `recurso.acción`,
p. ej. `month.approve`), **roles** (paquetes de permisos, ajustables
por organización), **grupos** (agrupación de presentación sin efecto
funcional) y **miembros** (asignación de roles).

## Requisitos

- Derechos de administración de la organización.
- Una cuenta de prueba sin derechos de admin para verificar de verdad
  los recortes.
- Claridad sobre los perfiles de trabajo (campo, jefatura de equipo,
  contabilidad…).

## Procedimiento recomendado

1. **Crear o copiar un rol** — partir de un rol existente ahorra
   intentos fallidos.
2. **Recortar permisos:** mejor un rol estrecho adicional que un
   derecho comodín amplio (principio de mínimo privilegio).
3. **Asignarlo a los miembros.**
4. **Verificar con la cuenta de prueba** antes de generalizar el rol.

![Gestión de roles con roles de sistema y número de permisos](media/administration/rollen.png)
*La gestión de roles: los roles de sistema de la organización con su número de permisos.*

## Ejemplo práctico

Para una nueva administrativa se copia el rol «oficina» de «jefatura
de equipo», se le quitan los derechos de aprobación y se asigna. La
prueba con la cuenta de control muestra: aprobaciones mensuales
invisibles, creación de órdenes operativa — exactamente lo previsto.

## Errores habituales

- **Asignar un rol admin global:** un rol sin vínculo a una
  organización actúa **en toda la plataforma**, sobre todos los
  inquilinos. Pertenece en exclusiva al operador y jamás debe
  asignarse por derechos delegables o por la interfaz de la
  organización — riesgo de escalada.
- **Esperar un atajo de admin:** los módulos sensibles (protección de
  datos, canal de denuncias) exigen asignación explícita — también a
  los admins. Es intencionado.
- **Dejar proliferar roles comodín:** cómodos, pero casi imposibles
  de recortar después.

## Efectos y próximos pasos

Los cambios de rol surten efecto inmediato para todos los miembros
asignados — también en menús, contenidos de ayuda y acceso a módulos.
Después: mantener las asignaciones en «Miembros» y leer las notas de
seguridad del manual de administración.
