---
title: "Cuentas de tiempo (administración)"
topic: admin.time-accounts
version: 1
audience: [admin]
related:
    - time-accounts.overview
---

Las cuentas de tiempo adicionales convierten datos existentes en cuentas
gestionadas: contadores de turnos nocturnos, cuentas de ahorro de tiempo
libre, acumuladores de pluses. El horario flexible y las vacaciones siguen
siendo cuentas propias y no se duplican aquí.

Por cuenta se define la unidad (minutos, días, cantidad), umbrales de
semáforo opcionales y la regla de arrastre: acumulativa o con tope al
cierre mensual. Las reglas de contabilización definen la fuente de forma
declarativa: patrones de tipo salarial del motor de reglas, asistencia
neta, días de ausencia, un contador por tipo de turno o cantidades de
posiciones externas importadas; un factor pondera (p. ej. 1,25 para «la
hora nocturna cuenta 1:1,25»).

La ejecución diaria contabiliza de forma idempotente; el diario es
inmutable: las correcciones son contraasientos de anulación, los asientos
manuales requieren justificación y se auditan. Opcionalmente el saldo
aparece en la respuesta de estado del terminal.
