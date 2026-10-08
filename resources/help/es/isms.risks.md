---
title: "Registro de riesgos"
topic: isms.risks
version: 3
keywords:
    - análisis de riesgos
    - evaluación de riesgos
    - matriz de riesgos
    - añadir riesgo
    - mapa de riesgos
    - tratamiento de riesgos
    - riesgo residual
    - aceptación del riesgo
    - probabilidad
    - riesgo inherente
    - riesgo neto
audience: []
modules:
    - module.isms
related:
    - isms.controls
    - isms.overview
    - isms.audits
    - glossary.core
---

En el **Registro de riesgos** usted registra, evalúa (5×5) y trata los
riesgos de seguridad de la información por alcance. Lo encontrará en
**SGSI** → **Gobernanza** → **Registro de riesgos**.

Proceso habitual:

1. **Añadir riesgo**: **Título**, **Categoría** («Organizativo»,
   «Técnico», «Físico», «Personal», «Proveedor»), **Referencia
   (sistema/proceso/ubicación)**, **Amenaza** (la amenaza o
   vulnerabilidad subyacente), **Responsable** y **Revisión prevista**.
2. **Evaluar**: **Probabilidad** (1–5) × impacto (1–5) da la
   **Puntuación** (1–25). Semáforo de la matriz de riesgos: Bajo
   (puntuación ≤ 6), Medio (puntuación 7–12), Alto (puntuación > 12).
3. Elegir el **Tratamiento**: «Evitar», «Mitigar», «Transferir» o
   «Aceptar», y asignar medidas en **Medidas vinculadas**.
4. Mantener el **Estado** con **Cambiar estado** a lo largo de la
   cadena: «Identificado» → «Analizado» → «Tratado»/«Aceptado» →
   «Cerrado». Un riesgo cerrado puede volver a «Analizado».

Historial de evaluaciones:

- Con **Registrar evaluación** crea una evaluación; el **Tipo de
  evaluación** es «Bruto», «Neto» u «Objetivo». Cada evaluación tiene una
  **Justificación**, opcionalmente una fecha **Válido hasta** (fecha de
  vencimiento o revisión) y pasa de «Borrador» a «Aprobada»
  (**Aprobar**).
- **Las evaluaciones aprobadas son inmutables.**
- La evaluación **neta** aprobada más reciente determina los valores que
  se muestran en el riesgo. Si cambia la probabilidad o el impacto
  directamente en el riesgo, se crea automáticamente una evaluación
  directa aprobada; el historial sigue completo.

Regla importante: el cambio a **«Aceptado»** (aceptación del riesgo
residual) exige una evaluación neta aprobada **con fecha «Válido
hasta»**.

Permisos: la consulta requiere el permiso **Ver registros del SGSI
(riesgos, medidas, DdA)**; los cambios requieren **Gestionar el SGSI
(riesgos, medidas, importación del catálogo)**.

Próximos pasos: cuando la fecha **Válido hasta** de la evaluación neta
aprobada más reciente de un riesgo abierto se acerca o ha vencido, se
notifica a la persona responsable; las **Reglas de notificación**
permiten además escalarlo. El campo **Revisión prevista** del riesgo
sirve para planificar y ordenar, pero no genera por sí mismo ninguna
notificación.
