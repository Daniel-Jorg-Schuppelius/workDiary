---
title: "Ejecutar un procedimiento"
topic: procedures.run
version: 2
keywords:
    - completar checklist
    - seguir instrucciones
    - iniciar procedimiento
    - pasos obligatorios
    - registrar desviación
    - contrafirma
    - doble verificación
    - prueba de copia de seguridad
    - confirmar paso
audience: []
related:
    - protocols.create
---

Un procedimiento se inicia en una **orden** o un **activo**. Los pasos
obligatorios deben completarse en el orden definido, los pasos de copia
de seguridad exigen la evidencia (hash y tamaño o enlace externo) y los
pasos de cuatro ojos requieren la confirmación de una segunda persona
con el rol adecuado. Las desviaciones se registran con justificación y
acción de seguimiento; no es posible omitir sin justificar. Al cerrar
el procedimiento se fija la versión de la plantilla utilizada y los
cambios posteriores no tienen efecto retroactivo.

## Pasos de cuatro ojos

La barra **Aprobación de cuatro ojos** del paso muestra el estado: aún no
solicitada, solicitada, asumida o refrendada.

1. Quien ejecuta el paso pulsa **Solicitar segunda persona**. Los
   responsables de equipo reciben una notificación con enlace a la
   ejecución.
2. Otra persona autorizada abre la ejecución y pulsa **Refrendar como
   segunda persona**. Quien la solicitó no puede refrendar.
3. Solo entonces se puede completar el paso.

Un refrendo se puede **revocar** con un motivo (permiso «Revocar la
aprobación a cuatro ojos», normalmente responsables o administración).
El paso vuelve a «abierto», la solicitud se mantiene y otra persona puede
refrendar. Solicitud, refrendo y revocación quedan registrados con
persona y hora en el historial de la ejecución.
