---
title: "SLA, contratos y niveles de servicio"
topic: sla.overview
version: 4
keywords:
    - acuerdo de nivel de servicio
    - tiempo de respuesta
    - tiempo de resolución
    - contrato de servicio
    - incumplimiento de SLA
    - plazo vencido
    - escalado
    - ticket atrasado
    - tasa de cumplimiento
    - informe SLA
audience: []
related:
    - glossary.core
---

Los contratos SLA (Service Level Agreements) guardan, por cliente o como
**Contrato predeterminado** para todos los clientes, los plazos de respuesta y
de resolución acordados por prioridad (**Plazos por prioridad**),
opcionalmente con **Horario laboral**; sin él, los plazos corren en
tiempo natural. Encontrará los contratos en **Service desk** →
**Contratos SLA**. A partir de estos valores objetivo, WorkDiary deduce
el estado SLA de un ticket de servicio y documenta los incumplimientos de
forma inalterable.

## Estado SLA en el ticket

Cada ticket de servicio con un plazo SLA muestra su estado de resolución
como distintivo:

- **SLA en plazo**: queda tiempo suficiente hasta el plazo de
  resolución.
- **SLA en riesgo**: el tiempo restante es como máximo el 20 % del plazo
  total.
- **SLA incumplido**: el plazo se ha superado (o el ticket se confirmó o
  resolvió demasiado tarde).
- **SLA cumplido**: el ticket se resolvió a tiempo.

Los tickets sin plazo muestran «Sin SLA». El plazo de respuesta se evalúa
del mismo modo y se comprueba en la primera confirmación.

## Registro de incumplimientos y detección

Los plazos superados se registran en un registro de incumplimientos,
exactamente una vez por ticket y tipo («Tiempo de respuesta» o «Tiempo de
resolución»). Se detectan:

1. en la comprobación automática de los tickets abiertos, que de forma
   predeterminada se ejecuta cada cinco minutos,
2. en los cambios de estado, cuando la primera respuesta o la resolución
   llega demasiado tarde.

A cada incumplimiento se le puede asignar una **Causa** en la **Lista de
incumplimientos** del informe SLA y marcarlo con **Confirmar**; para ello
se necesita el permiso **Confirmar incumplimientos de SLA**.

## Escalado

La comprobación automática notifica a la persona asignada los tickets en
riesgo e incumplidos. Si el evento sigue sin resolverse, WorkDiary escala
según las **Reglas de notificación** de la organización al rol de
escalado configurado allí (de forma predeterminada, los jefes de
equipo). Además, WorkDiary aplica los niveles guardados en **Escalado**
en el contrato SLA.

## Informe de SLA

El **Informe de SLA** (**Análisis** → **Proyectos y clientes** → **SLA**)
muestra para el periodo elegido los **Tickets con SLA**, la **Tasa de
cumplimiento** y los **Incumplimientos**, desglosados **Por tipo**, **Por
prioridad**, **Por cliente** y **Por causa**, además de una **Lista de
incumplimientos** con salto al ticket y las **Cuotas de tiempo
incluido**. El informe puede exportarse en PDF, CSV y Excel. Puede
consultarlo quien tenga el permiso **Ver estado e informe de SLA**; para
exportarlo se requiere además el permiso **Exportar los informes**; sin él no
aparecen los botones de exportación. Los administradores pueden hacer ambas
cosas siempre.
