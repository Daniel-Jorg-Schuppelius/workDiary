---
title: "Rondas de vigilancia"
topic: patrols.overview
version: 1
audience: []
related:
    - dispatch.overview
---

Una **ronda** es una lista ordenada de **puntos de control** con ventanas
previstas relativas al inicio («punto 3: +20 min ± 10»). El **escaneo
acredita punto y hora** — la prueba fiable ante los clientes (vigilancia,
facility, servicio invernal).

## Tokens

Cada punto de control recibe un **token** (impreso en la etiqueta/como QR).
Solo se guarda el hash; el texto claro aparece exactamente una vez — al
crearlo. **Una etiqueta perdida** se sustituye con «reemitir token»: token
nuevo, misma ruta, el antiguo queda inservible de inmediato.

## Ejecución

Iniciar la ronda → escanear tokens (el escáner de cámara teclea como
teclado, o entrada manual) → finalizar. Como máximo una ronda por ruta a la
vez; los escaneos dobles cuentan una vez.

## Desviaciones

Los puntos omitidos o escaneos fuera de ventana se **muestran, nunca se
alisan** — y el cierre exige entonces una **justificación**. Además se crea
un **punto abierto** para la central (vence al día siguiente) — la
escalada corre por el sistema existente, sin canal propio.

Las horas previstas son **prueba, no métrica de presión**: sin datos de
posición en el escaneo ni evaluación personal duradera.
