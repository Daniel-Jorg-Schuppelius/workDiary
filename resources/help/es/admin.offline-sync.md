---
title: "Sincronización sin conexión"
topic: admin.offline-sync
version: 1
audience: []
related:
    - admin.metrics
---

Quien trabaja fuera sin red registra en una **bandeja de salida del
dispositivo**; en cuanto vuelve la conexión, el dispositivo transmite las
órdenes. Esta página muestra **cada orden transmitida con su resultado** — la
respuesta a qué datos nacieron sin conexión y si llegaron.

## Los cuatro resultados

- **Aplicado** — la orden está en los datos. El caso normal.
- **Duplicado** — el mismo dispositivo envió dos veces la misma orden (típico
  tras un corte en plena transmisión). No es un error: la orden se aplicó la
  primera vez, la repetición se reconoció y se descartó.
- **Conflicto** — los datos cambiaron entre tanto; la orden **no** se aplicó.
- **Rechazado** — la orden era inválida (por ejemplo un fichaje en un estado
  no permitido); la columna de errores indica el motivo.

**Conflicto y Rechazado son la razón de esta página:** esos registros *no*
llegaron a los datos. Los contadores del filtro de resultados cuentan siempre
el total — un filtro puesto no los oculta.

## Las dos marcas de tiempo

**Registrado (sin conexión)** es la hora del dispositivo, **Transmitido** la
llegada al servidor. La distancia entre ambas es la latencia sin conexión — un
día es normal en campo, una semana indica un dispositivo que no sincroniza.
