---
title: "Cierre del día"
topic: time-entries.day-close
version: 3
keywords:
    - cerrar el día
    - fin de jornada
    - añadir pausa
    - balance diario
    - saldo del día
    - huecos de tiempo
    - pausa obligatoria
    - fichaje abierto
    - solicitar corrección
    - completar horas
audience: []
related:
    - time-entries.start
    - attendance.manage
    - time-accounts.flex
---

El **Cierre diario** reúne en la página **Hoy** (menú **Operativa diaria**
→ **Registro** → **Hoy**) todo lo que pertenece a una jornada laboral:
**Fichajes**, pausas, **Registros de tiempo**, las comprobaciones de
**Lagunas y avisos** y el **Balance** (entre otros, **Presencia (bruta)**,
**Pausa obligatoria**, **Saldo del día** y **Saldo del mes en curso**).

Cómo proceder:

1. **Comprobar**: abra la página al final de la jornada; con **Día
   anterior** y **Día siguiente** pasa a otros días. Las lagunas e
   incoherencias aparecen en la sección **Lagunas y avisos**.
2. **Completar**: registre los tiempos que faltan en la barra de entrada
   superior (elegir un proyecto, indicar **Duración** o **Desde / Hasta**,
   **Registrar**). Asigne a un proyecto los bloques de presencia aún no
   imputados en **Registro rápido** con **Registrar**; allí, `Ctrl` +
   `Intro` registra el bloque y pasa al siguiente. Los fichajes en sí solo
   pueden modificarse mediante una solicitud de corrección.
3. **Cerrar**: cuando no quede ningún aviso ⛔, cierre la jornada con
   **Cerrar día**. **Guardar** conserva el estado sin cerrar el día.

Los avisos ⛔ bloquean el cierre: reloj de fichaje abierto, presencia no
imputada (más de 5 minutos) o pausa obligatoria no cumplida. Las
indicaciones ⚠ no bloquean, por ejemplo un saldo diario de más de ±2
horas, más de 10 horas de trabajo neto, una interrupción de la presencia
sin pausa o registros facturables sin comentario.

Tras el cierre, el día queda bloqueado para usted. Si necesita un cambio,
solicite una autorización mediante **Solicitar corrección** (motivo de al
menos 20 caracteres). Deciden las personas con el permiso **Aprobar
correcciones de cierres diarios** (de forma predeterminada, los jefes de
equipo) o los administradores, con **Aprobar** o **Rechazar**. Tras la
aprobación solo se pueden modificar los registros; los fichajes siguen
bloqueados. Quien tenga el permiso **Reabrir un cierre diario** también
puede desbloquear un día cerrado sin solicitud con **Reabrir día**; el
motivo se guarda en el registro de auditoría.

Los días de un mes ya aprobado están completamente bloqueados; allí
cualquier cambio pasa por la aprobación mensual.
