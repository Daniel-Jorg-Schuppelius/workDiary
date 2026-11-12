---
title: "Ejecutar un procedimiento"
topic: procedures.run
version: 4
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

## Prueba de copia de seguridad

Un paso del tipo «Registro de copia de seguridad» muestra la tarjeta
**Prueba de copia de seguridad**.

1. **Registrar prueba de copia de seguridad** abre el diálogo: sistema
   respaldado, alcance, momento de la copia, almacenamiento (**Adjunto**
   con archivo o **Almacenamiento externo** con ubicación y tamaño) y
   método de verificación. Lo que fija la plantilla no se ofrece; el tamaño
   mínimo y la antigüedad máxima se comprueban al guardar.
2. Con el método **Comparación de suma de verificación** usted indica la
   suma esperada. Para un archivo subido, el sistema recalcula la suma y
   confirma la prueba por sí mismo; con almacenamiento externo, la persona
   que verifica introduce la suma recalculada.
3. **Prueba de restauración** y **Confirmación de la dirección** las
   confirma una **segunda persona** con el permiso «Verificar un registro
   de copia de seguridad» mediante **Confirmar prueba**, no la persona que
   la registró.
4. **Rechazar prueba** exige un motivo; después registre una prueba nueva
   (**Sustituir prueba**).

Solo con una prueba confirmada se puede completar el paso. Un paso
posterior que exige una copia previa queda entonces libre. Si no es
posible hacer una copia, regístrelo como **desviación**. La ubicación de
una copia externa solo es visible con el permiso «Ver el almacenamiento de
copia de seguridad externo».

## Cualificación

Si un paso indica una **Cualificación**, solo puede completarlo quien posea
hoy esa cualificación vigente (abreviatura o nombre tal como figura en las
cualificaciones de los empleados). Lo mismo vale para la segunda persona
cuando la plantilla exige una cualificación para la contrafirma.

## Desviación crítica y aceptación del riesgo

Una desviación figura en el paso con tipo, gravedad, motivo y medida de
seguimiento. Una desviación **crítica** bloquea el cierre de la ejecución
mientras no haya surgido de ella ni un punto abierto ni una orden de
seguimiento. Quien tenga el permiso «Aceptar una desviación crítica» abre
**Aceptar riesgo** en la desviación e indica un motivo. La persona, el
momento y el motivo constan en el historial de la ejecución; el informe
«Desviaciones de procedimiento» muestra la decisión.

## Procedimientos críticos

La ejecución de una plantilla con nivel de riesgo «Crítica» solo se puede
cerrar si al menos un paso de cuatro ojos está completado y contrafirmado.

## Inicio en un activo

Las plantillas que el diseñador asigna por categoría de activo, producto o
contrato SLA aparecen en la ficha del activo, en la tarjeta
**Procedimientos**, bajo «Procedimientos previstos para este activo:». Si
el activo está bloqueado, solo se inicia una plantilla con la opción
**Puede ejecutarse en activos bloqueados**.

**Volver a ejecutar:** si elige esta acción de seguimiento para una desviación, WorkDiary inicia de inmediato una ejecución repetida de la misma plantilla en la misma orden o activo. La desviación remite a ella y una desviación crítica ya no bloquea el cierre. Si el activo está bloqueado, no se crea ninguna ejecución; el motivo figura en el historial.
