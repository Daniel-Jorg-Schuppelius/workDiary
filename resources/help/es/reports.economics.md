---
title: "Rentabilidad"
topic: reports.economics
version: 3
keywords:
    - cálculo a posteriori
    - margen de contribución
    - margen
    - rentabilidad de proyectos
    - comparación previsto real
    - comparación con presupuesto
    - coste interno por hora
    - proyectos deficitarios
    - control de gestión
    - top y flop
    - beneficio por cliente
audience: []
modules:
    - module.auswertungen_team
related:
    - reports.customer-analysis
    - reports.drilldown
---

La página **Rentabilidad** (poscálculo) en **Análisis** → **Finanzas y
auditoría** → **Rentabilidad** muestra el margen de contribución por
cliente (**Rentabilidad por cliente**) y por proyecto (**Rentabilidad &
previsto-vs-real por proyecto**) en el **Período** elegido:

- **Ingresos** = tiempos facturables × tarifa + material facturado +
  gastos facturables. La factura determinante la gestiona el sistema de
  facturación externo; aquí los importes registrados sirven como
  proyección.
- **Costes** = tarifa de coste interna del tiempo × tiempo + gastos
  directos de material y justificantes.
- **Margen de contribución** = ingresos − costes, también como **Margen**
  en porcentaje.

Otros análisis:

- **Clasificación**: «Top 5 clientes (margen de contribución)», «Peores 5
  clientes (margen de contribución)» y lo mismo para proyectos; así se
  ven los clientes y proyectos deficitarios.
- **Tiempo no facturable**: por cliente, **Facturable (min.)**, **No
  facturable (min.)** y **Proporción %** muestran cuánto tiempo se
  registró sin facturar, un indicio de retrabajo y cortesía comercial.
  Por proyecto, **Retrabajo (min.)**, **Cortesía comercial (min.)** y
  **Retrabajo %** indican el tiempo registrado con un motivo de retrabajo
  o de cortesía.
- **Previsto frente a real** por proyecto: **Real (min.)** frente a
  **Plan (min.)** del presupuesto de tiempo del proyecto (**Δ Min.**) y
  costes reales frente al **Presupuesto del plan** en euros
  (**Δ Presupuesto**).

Notas sobre la calidad de los datos:

- Si para una parte de los tiempos **no hay una tarifa de coste interna**,
  estos se incluyen con 0 € de costes; el margen de contribución resulta
  entonces demasiado optimista. Los costes llevan entonces un asterisco
  con el aviso «Tarifas de coste no completamente cumplimentadas».
- Los proyectos **sin presupuesto de tiempo/presupuesto** muestran «–»
  en las columnas del plan.

Exportación en **PDF**, **CSV** o **Excel** para dirección y control de
gestión. La página muestra datos financieros de toda la organización y
solo está disponible para personas con el permiso **Ver los informes**.
