---
title: "Gestionar hojas de horas"
topic: timesheets.manage
version: 3
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

**Material desde «Hoy»:** con «Material» en «Hoy» registra consumibles con proyecto, cantidad y unidad, también sin red. Se añaden a su parte de horas abierto del proyecto para ese día; si no existe (o ya está firmado), se crea uno nuevo. El material de un parte de horas no registra una salida de almacén.

**Límite de suma:** el material que registre en «Hoy» va a la hoja de horas abierta del día. Si cantidad × precio unitario, junto con las demás líneas, superara la suma almacenable de la hoja, el registro se rechaza, en línea y sin conexión.
