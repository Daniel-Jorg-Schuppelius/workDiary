---
title: "Reglas de recargo"
topic: admin.surcharge-rules
version: 3
keywords:
    - plus de nocturnidad
    - recargo nocturno
    - recargo dominical
    - recargo festivo
    - plus de fin de semana
    - plus de turno
    - concepto salarial
    - exportación de nóminas
    - DATEV
    - Lexware
    - horas nocturnas
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
modules:
    - module.lohn
related:
    - exports.payroll
    - finance.transfers
    - admin.handbook
    - glossary.core
---

Las reglas de recargo definen los recargos nocturnos, de fin de semana,
de días festivos y de franjas horarias personalizadas, así como los
recargos por guardia presencial, guardia localizada y horas extra. En
la exportación de tiempos, los tiempos se evalúan en consecuencia y se
muestran en líneas separadas para cada concepto salarial.

Procedimiento típico:

1. **Crear** abre el diálogo **Crear regla de recargo**. En **Datos
   básicos** introduzca el **Código** (único, p. ej. «night»), la
   **Denominación** (p. ej. «Recargo nocturno»), el **Tipo** y el
   **Recargo (%)** (0–999,99).
2. Elegir el **Tipo**: **Noche** (franja horaria, también pasada la
   medianoche, p. ej. 22:00–06:00), **Sábado**, **Domingo**, **Día
   festivo** (festivos legales de forma automática), **Personalizado**
   (franja horaria libre), **Guardia presencial**, **Guardia
   localizada** u **Horas extra**. Para Noche y Personalizado defina la
   **Franja horaria** con **Franja desde** y **Franja hasta**.
3. En **Traspaso de nómina**, indicar opcionalmente el **Concepto
   salarial** para DATEV/Lexware (p. ej. «2010») y la **Prioridad**.
   Con **Exento hasta (%)** y **Tipo de salario parte imponible** se
   reparte un recargo que supera el límite exento entre dos conceptos.
4. En **Validez**, establecer opcionalmente **Válida desde**/**Válida
   hasta** y activar **La regla está activa**; en **Condiciones** se
   limita la regla a **Equipos**, **Ubicaciones** o **Tipos de turno**.

Reglas importantes:

- Si se solapan reglas de los tipos Noche, Sábado, Domingo, Día festivo
  y Personalizado, prevalece el **porcentaje más alto**: los recargos
  no se suman. En caso de empate decide la prioridad.
- **Guardia presencial** evalúa las horas de las guardias registradas,
  **Guardia localizada** las entradas de tiempo del tipo Disponibilidad
  y **Horas extra** las horas que superan la jornada mensual prevista.
  Estos tres tipos no necesitan franja horaria, no se compensan con los
  demás recargos y aparecen en líneas propias. Por ahora, la
  exportación no evalúa para ellos ni la validez ni las condiciones.
- Las condiciones restringen una regla: vacío = se aplica a todos;
  varias condiciones se combinan con Y lógico y dentro de una lista
  basta una coincidencia. La ubicación se reconoce mediante los
  fichajes en terminal; sin un contexto determinable, una regla
  condicionada no se aplica. Las ubicaciones pueden tener su propia
  región de festivos (recargo festivo en el lugar de trabajo).
- Los cambios afectan a las **exportaciones futuras**; las
  exportaciones ya generadas no se modifican (corrección mediante una
  nueva exportación). Solo un recálculo auditado realizado por la
  administración técnica vuelve a evaluar los periodos pasados, nunca
  un cambio silencioso de las reglas.

Permisos: **Ver reglas de recargo** muestra la lista; solo las personas
con el permiso **Gestionar reglas de recargo** pueden crear, editar y
eliminar reglas.
