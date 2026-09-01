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

Aquí configuras el portal público de denuncias de tu organización
(`/compliance/portal`); hay exactamente un portal por organización y su
gestión requiere el permiso **whistleblowing.settings.manage** y la
autenticación de dos factores del canal de denuncias. Ajustes: **activo
(`is_enabled`)**, permitir denuncias **anónimas** y **confidenciales**,
texto de introducción, idioma por defecto y **retención (meses)** para
la eliminación controlada de casos cerrados. El enlace público contiene
un slug aleatorio no deducible del nombre de la organización; con
**«Rotar enlace»** generas uno nuevo. Atención: tras rotar, los enlaces
ya distribuidos quedan inválidos de inmediato — comunica el nuevo
enlace activamente.
