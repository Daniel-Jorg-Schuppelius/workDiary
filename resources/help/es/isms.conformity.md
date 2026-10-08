---
title: "Certificaciones y conformidad normativa"
topic: isms.conformity
version: 2
keywords:
    - ISO 27001
    - certificado ISO
    - auditoría de certificación
    - auditoría de seguimiento
    - recertificación
    - análisis de brechas
    - entidad certificadora
    - certificado caducado
    - compliance
    - cumplimiento normativo
audience: []
modules:
    - module.isms
related:
    - isms.audits
    - isms.requirements-soa
    - isms.overview
    - glossary.core
---

En la página **«Certificaciones»** gestiona el estado de conformidad por
norma y alcance, desde el análisis de brechas hasta el certificado
registrado.

Proceso habitual:

1. **Añadir norma** (p. ej., ISO/IEC 27001, edición 2022) por alcance.
2. Avanzar el estado a lo largo de la cadena: «No evaluado» → «Análisis
   de brechas realizado» → «En implementación» → «Listo para auditoría
   interna» → «Auditoría externa planificada» → **«Certificado»**. Es
   posible volver a «En implementación».
3. **Registrar certificado**: organización certificada, alcance según el
   certificado, entidad de certificación, número de certificado, fecha de
   emisión, válido desde/hasta; opcionalmente, las fechas de las
   auditorías de seguimiento y el PDF del certificado.

Reglas importantes:

- El cambio a **«Certificado»** solo es posible con un certificado
  **válido hoy** en el que estén cumplimentados todos los campos
  obligatorios. Un nivel de madurez, una lista de comprobación completa
  o la ausencia de medidas abiertas **nunca** activan el estado
  **automáticamente**.
- Si el certificado caduca, el escáner de plazos cambia automáticamente
  el estado a **«Certificado caducado»**. La suspensión («Certificado
  suspendido») y la reanudación pueden reflejarse; la recertificación
  comienza a través de «Auditoría externa planificada».

Permisos: la consulta requiere permisos de lectura del SGSI; los cambios
requieren permisos de gestión del SGSI.
Los certificados que vencen pueden notificarse mediante reglas de
notificación.
