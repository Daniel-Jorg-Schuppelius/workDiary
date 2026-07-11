---
title: "Reglas de centros de coste"
topic: admin.cost-center-rules
version: 1
audience:
    - admin
related:
    - exports.payroll
    - org.teams
---

Las reglas de centros de coste asignan automáticamente un **centro de
coste** a los registros de tiempo durante la exportación de tiempos
(p. ej. para la gestoría de nóminas), sin que nadie tenga que retocar
manualmente cada registro.

**Estructura de una regla:** Cada regla consta de exactamente **una
fuente**: un usuario **o** un equipo; si ambos quedan vacíos, la regla
actúa como **estándar de la organización**. A ello se añaden el código
del centro de coste y una prioridad. Las reglas las mantienen los
administradores, así como contabilidad/gestoría de nóminas con el
permiso correspondiente.

**Orden de resolución:** En la exportación, para cada persona se
resuelve de la regla más específica a la más general:

- **Regla de usuario** – gana siempre que exista.
- **Regla de equipo** – se aplica si la persona es miembro del equipo.
- **Estándar de la organización** – la regla sin usuario ni equipo.
- Si ninguna regla coincide, el centro de coste queda **vacío** en la
  exportación.

**La prioridad como desempate:** Si en el mismo nivel entran en juego
varias reglas (p. ej. porque una persona pertenece a varios equipos con
regla propia), gana la regla con la **prioridad más alta**; a igual
prioridad, la creada primero. Asigne por tanto intervalos de prioridad
con margen (p. ej. saltos de 100) para poder intercalar reglas más
adelante.

**Interacción con los datos maestros:** Los centros de coste se
gestionan como datos maestros con código y denominación por
organización. Actualmente las reglas almacenan el código como texto:
asegúrese por ello de que los códigos de las reglas coincidan con los
datos maestros y ajuste las reglas si renombra o desactiva centros de
coste.

**Recomendación:** Empiece con un estándar de la organización, añada
reglas de equipo para departamentos con centro de coste propio y use
reglas de usuario solo para excepciones reales. Tras los cambios,
compruebe una exportación de prueba antes de que los datos lleguen a la
gestoría de nóminas.
