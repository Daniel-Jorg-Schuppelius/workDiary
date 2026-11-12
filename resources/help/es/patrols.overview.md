---
title: "Rondas de vigilancia"
topic: patrols.overview
version: 2
keywords:
    - ronda de seguridad
    - control de rondas
    - puntos de control
    - escanear punto
    - prueba de paso
    - vigilante
    - servicio de seguridad
    - código QR
    - punto omitido
    - cancelar ronda
audience: []
modules:
    - module.planung
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

## Interrupción

Una ronda en curso puede **interrumpirse** — solo con un **motivo**. Entonces
no cuenta como finalizada; los puntos de control confirmados se conservan
como prueba, y el informe refleja la interrupción con motivo, persona y hora.
Los puntos abiertos pasan a la central como **punto abierto**, igual que una
desviación. Después la ruta vuelve a quedar libre.

## Desviaciones

Los puntos omitidos o escaneos fuera de ventana se **muestran, nunca se
alisan** — y el cierre exige entonces una **justificación**. Además se crea
un **punto abierto** para la central (vence al día siguiente) — la
escalada corre por el sistema existente, sin canal propio.

Las horas previstas son **prueba, no métrica de presión**: sin datos de
posición en el escaneo ni evaluación personal duradera.

## Mantener rutas y puntos de control

**Editar** cambia la denominación, el objeto y la **persona responsable** de la
ruta. Una ruta se puede **desactivar**: una ronda en curso puede terminar, no
se pueden iniciar nuevas. Los puntos de control se pueden editar (lugar,
previsto, tolerancia; los escaneos registrados conservan su valoración) y
**desactivar**: a partir de entonces ya no se esperan ni se pueden escanear.
Las rondas anteriores no cambian: una ronda iniciada antes de la desactivación
sigue esperando el punto.

## Escalado

El punto abierto se crea **en el mismo momento de la desviación** —con el
primer escaneo fuera de la ventana o en cuanto se salta un punto vencido— y
va a la persona responsable de la ruta o, si no la hay, a la jefatura de equipo
de quien realiza la ronda. Hay un punto abierto por ronda, que se actualiza
hasta el final. Si una ronda sigue en curso tras la duración prevista más la
tolerancia del último punto de control, el proceso de plazos la notifica una
vez como **no finalizada**; la central puede entonces cancelarla con
motivo.
