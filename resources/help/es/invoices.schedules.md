---
title: "Planes de facturación"
topic: invoices.schedules
version: 1
audience:
    - admin
related:
    - invoices.manage
---

Los **planes de facturación** crean **borradores** de facturas recurrentes
(MVP-415) — la emisión y el envío siguen siendo pasos manuales.

- **Ritmo**: semana/mes/trimestre/año × cantidad; el período de facturación es
  el vencido o el en curso (pago anticipado).
- **Plantilla de posiciones**: los marcadores `{zeitraum_von}` y
  `{zeitraum_bis}` se sustituyen en cada ejecución; los descuentos se conservan.
- **Contrato**: vinculación opcional — si el contrato termina, el plan termina.
- **Idempotente**: como máximo un borrador por plan y período; las ejecuciones
  perdidas se recuperan.
- **Soberanía de facturación**: si un sistema externo gestiona las facturas
  del cliente, el plan queda visiblemente bloqueado.
