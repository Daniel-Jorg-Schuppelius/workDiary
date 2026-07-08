---
title: "SLA, contratos y niveles de servicio"
topic: sla.overview
version: 1
audience: []
related:
    - glossary.core
---

Los contratos SLA definen por cliente o como estándar los plazos de
reacción y resolución por prioridad; WorkDiary deriva de ellos el
estado SLA de cada ticket de servicio. Cada ticket muestra una insignia:
**SLA en plazo**, **SLA en riesgo** (menos del 20 % de tiempo restante)
o **SLA incumplido**. Los incumplimientos se registran una sola vez por
ticket y tipo en un registro de infracciones, detectados por el escaneo
nocturno o en los cambios de estado, y pueden confirmarse con una
causa. El escáner de plazos notifica al responsable del ticket y, como
escalado, a la jefatura de equipo; el informe SLA (Informes → SLA)
muestra la cuota de cumplimiento y es exportable a CSV y PDF.
