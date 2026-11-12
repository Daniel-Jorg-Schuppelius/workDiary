---
title: "Configurar el portal de denuncias"
topic: whistleblowing.portal
version: 2
keywords:
    - portal del denunciante
    - activar portal
    - permitir denuncias anónimas
    - denuncias confidenciales
    - enlace del portal
    - regenerar enlace
    - plazo de conservación
    - texto introductorio
    - configurar canal ético
    - idioma predeterminado
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

**Idiomas**: el **idioma predeterminado** determina en qué idioma aparece el portal. En **Otros idiomas** elige a qué idiomas puede cambiar la persona informante; sin selección no hay cambio de idioma.

**Canales externos de denuncia**: el portal indica siempre que, además del canal interno, están disponibles los canales externos de las autoridades. Introduzca los organismos competentes para su organización con denominación y dirección web; aparecen como lista en el portal.

**Versión del aviso de privacidad**: el dato aparece bajo el texto introductorio para que se sepa qué versión se aplica.

**Conservación**: los meses definidos aquí se aplican a cada caso que se cierre a partir de ahora. Los casos ya cerrados conservan el plazo calculado al cerrarlos.
