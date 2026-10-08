---
title: "Herramientas de protección de datos"
topic: admin.privacy-tools
version: 2
keywords:
    - RGPD
    - privacidad
    - sesiones activas
    - revocar sesión
    - cierre de sesión forzado
    - revocar token API
    - portabilidad de datos
    - solicitud de acceso
    - plazos de conservación
    - exportación de datos
audience:
    - admin
    - geschaeftsfuehrung
    - support
related:
    - admin.security
    - admin.handbook
    - privacy.overview
---

Esta sección reúne en una sola página las herramientas relacionadas con
la protección de datos de su organización. Está protegida por permisos
(permiso de protección de datos) y abarca toda la organización, no solo
su propia cuenta.

Resumen del estado:

- Miembros activos, sesiones activas y tokens de API de un vistazo.
- Categorías de datos con sensibilidad, plazos de conservación y vía
  de eliminación.

Sesiones activas:

- Tabla de las sesiones iniciadas de los miembros de la organización
  con dirección IP, navegador/dispositivo y última actividad.
- Las sesiones pueden **revocarse** de forma individual (cierre de
  sesión forzado).

Tokens de API:

- Resumen de los tokens de acceso personales (nombre, usuario, fecha de
  creación, último uso, caducidad).
- Los tokens pueden **revocarse**. Los tokens revocados pierden su
  validez de inmediato.

Registros:

- Últimos eventos de exportación del inquilino (quién, cuándo,
  formato/alcance).
- Últimos accesos de soporte (mantenimiento remoto/soporte) para
  garantizar la trazabilidad.

Exportación de datos:

- Genera un informe legible por máquina (JSON/CSV) con los metadatos de
  la organización, las sesiones, los tokens y los registros de
  exportación y de soporte, como base para las solicitudes de acceso y
  de portabilidad (art. 20 RGPD) a nivel de organización.

Riesgos: la revocación de una sesión o de un token surte efecto de
inmediato y puede interrumpir integraciones o inicios de sesión en
curso. La exportación contiene datos administrativos de carácter
personal: trátela de forma confidencial y entréguela solo a personas
autorizadas.
