---
title: "Reglas de recargo"
topic: admin.surcharge-rules
version: 1
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

Las reglas de recargo definen suplementos nocturnos, de fin de semana,
festivos y de franjas horarias personalizadas; en el exporte de
tiempos las presencias se desglosan según ellas y se emiten como
líneas propias por tipo de salario. Cree una regla con código, nombre
y **tipo** («Noche», «Sábado», «Domingo», «Festivo» o
«Personalizado»), indique el **porcentaje** (0–999,99 %) y
opcionalmente el número de tipo de salario para DATEV/Lexware, la
vigencia, la prioridad y el estado activo. En reglas solapadas gana el
**porcentaje más alto** (no se suman) y las franjas horarias solo
aplican a «Noche» y «Personalizado». Los cambios afectan solo a
exportes futuros y la edición requiere permisos expresos.
