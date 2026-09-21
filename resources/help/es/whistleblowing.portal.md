---
title: "Configurar el portal de denuncias"
topic: whistleblowing.portal
version: 1
audience:
    - admin
modules:
    - module.compliance
related:
    - whistleblowing.cases
    - whistleblowing.report
    - admin.security
    - privacy.overview
---

Aquí configura el portal público de denuncias de su organización
(`/compliance/portal`); hay exactamente un portal por organización y su
gestión requiere el permiso **whistleblowing.settings.manage** y la
autenticación de dos factores del canal de denuncias. Ajustes: **activo
(`is_enabled`)**, permitir denuncias **anónimas** y **confidenciales**,
texto de introducción, idioma por defecto y **retención (meses)** para
la eliminación controlada de casos cerrados. El enlace público contiene
un slug aleatorio no deducible del nombre de la organización; con
**«Rotar enlace»** genera uno nuevo. Atención: tras rotar, los enlaces
ya distribuidos quedan inválidos de inmediato — comunique el nuevo
enlace activamente.
