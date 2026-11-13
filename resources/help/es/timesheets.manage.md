---
title: "Gestionar hojas de horas"
topic: timesheets.manage
version: 4
keywords:
    - parte de horas
    - parte de trabajo
    - hoja de servicio
    - informe de horas
    - justificante de trabajo
    - albarán de trabajo
    - firma del cliente
    - recoger firma
    - registrar material
    - facturar servicios
audience: []
related:
    - time-entries.start
    - protocols.sign
    - finance.transfers
---

Las hojas de horas agrupan las prestaciones de un proyecto o periodo
para su revisión, firma y facturación; las posiciones pueden incluir
tiempos, actividades y material. Flujo típico: elegir el proyecto,
registrar las posiciones, comprobar los totales, presentar la hoja para
la firma y cerrarla tras la confirmación. Los contenidos ya firmados no
deben modificarse en silencio; las correcciones se hacen de forma
trazable mediante una nueva versión. Antes de la aprobación deben ser
correctos periodo, cliente, proyecto, descripción y cantidades; el PDF
es una salida del estado guardado, no la fuente de datos principal.

**Material desde «Hoy»:** con «Material» en «Hoy» registra consumibles con proyecto, cantidad y unidad, también sin red. Se añaden a su parte de horas abierto del proyecto para ese día; si no existe (o ya está firmado), se crea uno nuevo.

**Salida de almacén:** si el material está vinculado a un artículo con gestión de existencias (artículo marcado como «Almacenable») y el módulo de almacén está activo, el consumo registra la salida del almacén predeterminado de la organización, en la unidad introducida y valorada según el método de valoración del almacén. Si cambia la cantidad, solo se registra la diferencia; otro material devuelve el artículo anterior, y eliminar la línea o todo el parte de horas devuelve la salida. Bajo la descripción, la línea muestra si se registró y desde qué almacén. Si las existencias no bastan, se rechaza el registro; con el permiso «Autorizar existencias negativas» marca «Permitir stock negativo». Si la organización exige cuatro ojos para ello, la salida espera la aprobación en la bandeja de aprobaciones («Existencias negativas solicitadas»). Los artículos con número de serie se retiran a través del almacén; los artículos con lote se toman de los lotes, primero la fecha de caducidad más próxima. Sin almacén predeterminado, la línea muestra «No registrado – sin almacén predeterminado»; si las existencias se llevan en otro sistema solo en lectura, WorkDiary no registra nada. Si otro sistema gestiona las existencias, cada movimiento se le envía también. Lo mismo vale sin conexión: si las existencias impiden el registro, la entrada aparece como conflicto en «Cambios sin conexión».

**Límite de suma:** el material que registre en «Hoy» va a la hoja de horas abierta del día. Si cantidad × precio unitario, junto con las demás líneas, superara la suma almacenable de la hoja, el registro se rechaza, en línea y sin conexión.
